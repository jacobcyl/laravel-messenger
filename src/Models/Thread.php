<?php

namespace Jacobcyl\Messenger\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Jacobcyl\Messenger\Exceptions\IncorrectUsageException;

class Thread extends Eloquent
{
    use SoftDeletes;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'threads';

    /**
     * The attributes that can be set with Mass Assignment.
     *
     * @var array
     */
    protected $fillable = ['subject', 'to_all', 'cate'];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    /**
     * Messages relationship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function messages()
    {
        return $this->hasMany('Jacobcyl\Messenger\Models\Message', 'thread_id', 'id')->with('user');
    }

    /**
     * Returns the latest message from a thread.
     *
     * @return \Jacobcyl\Messenger\Models\Message
     */
    public function getLatestMessageAttribute()
    {
        if( isset($this->messages) )
            return $this->messages->last();
        else
            return $this->messages()->latest()->first();
    }
    
    public function getLastReadAttribute(){
        if ( isset($this->pivot->last_read) )
            return $this->pivot->last_read;
        else
            throw new IncorrectUsageException('Incorrect usage of get thread last_read, please use the trait attached to User model, 
            E.g: $user->thread->last_read');
    }

    /**
     * Participants relationship.
     *
     * @param null $userId
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function participants($userId = null)
    {
        return $this->hasMany('Jacobcyl\Messenger\Models\Participant', 'thread_id', 'id')->ofUser($userId);
    }


    /**
     * get users through participants
     * @param null $userId
     * @return mixed
     */
    public function participantUsers($userId = null){

        $users = $this->belongsToMany(config('messenger.user_model', 'App\Models\User'), 'participants', 'thread_id', 'user_id');

        if( !empty($userId) ){
            $users = $users->wherePivot('user_id', $userId);
        }
        return $users;
    }

    /**
     * Returns the user object that created the thread.
     *
     * @return mixed
     */
    public function creator()
    {
        if( isset($this->messages) )
            return $this->messages->first()->user;
        else
            return $this->messages()->oldest()->first()->user;
    }

    /**
     * Returns all of the latest threads by updated_at date.
     *
     * @return mixed
     */
    public static function getAllLatest()
    {
        return self::latest('updated_at');
    }

    /**
     * Returns all threads by subject.
     *
     * @return mixed
     */
    public static function getBySubject($subjectQuery)
    {
        return self::where('subject', 'like', $subjectQuery)->get();
    }

    /**
     * Returns an array of user ids that are associated with the thread.
     *
     * @param null $userId
     *
     * @return array
     */
    public function participantsUserIds($userId = null)
    {
        $users = $this->participants()->withTrashed()->lists('user_id');

        if ($userId) {
            $users[] = $userId;
        }

        return $users;
    }

    /**
     * Returns threads that the user is associated with.
     *
     * @param $query
     * @param $userId
     *
     * @return mixed
     */
    public function scopeForUser($query, $userId)
    {
        return $query->whereHas('participants', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        });
    }

    /**
     * Returns threads with new messages that the user is associated with.
     *
     * @param $query
     * @param $userId
     *
     * @return mixed
     */
    public function scopeWithNewMessages($query)
    {
        return $query->whereHas('participants', function ($query){
            $query->whereNull('last_read')
                ->orWhere('threads.updated_at', '>', 'last_read');
        });
    }

    /**
     * Returns threads between given user ids.
     *
     * @param $query
     * @param $participants
     *
     * @return mixed
     */
    public function scopeBetween($query, array $participants)
    {
        $query->whereHas('participants', function ($query) use ($participants) {
            $query->whereIn('user_id', $participants)
                ->groupBy('thread_id')
                ->havingRaw('COUNT(thread_id)=' . count($participants));
        });
    }

    /**
     * Adds users to this thread.
     *
     * @param array $participants list of all participants
     */
    public function addParticipants($participants)
    {
        if (is_array($participants) && count($participants)) {
            $all = [];
            foreach ($participants as $user_id) {
                $participant = [
                    'user_id' => $user_id,
                    'thread_id' => $this->id,
                ];
                $all[] = $participant;
            }
            DB::table('participants')->insert($all);
        } else if(!empty($participants)) {
            Participant::firstOrCreate([
                'user_id' => $participants,
                'thread_id' => $this->id,
            ]);
        }
    }


    /**
     * Mark a thread as read for a user.
     *
     * @param int $userId
     */
    public function markAsRead($userId)
    {
        try {
            $participant = $this->getParticipantFromUser($userId);
            $participant->update(['last_read' => new Carbon()]);
        } catch (ModelNotFoundException $e) {
            Log::error('Mark message as read fail:');
            Log::error($e->getMessage());
        }
    }

    /**
     * See if the current thread is unread by the user.
     *
     * @param int $userId
     *
     * @return bool
     */
    public function isUnread($userId = null)
    {
        try {
            if(isset($this->pivot->last_read) || $this->pivot->last_read === null) {
                $last_read = $this->pivot->last_read;
            } else {
                $participant = $this->getParticipantFromUser($userId);
                $last_read = $participant->last_read;
            }

            if ($this->updated_at > $last_read)
                return true;

        } catch (ModelNotFoundException $e) {
            Log::error('isUnread:' . $e->getMessage());
        }

        return false;
    }

    /**
     * Finds the participant record from a user id.
     *
     * @param $userId
     *
     * @return mixed
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function getParticipantFromUser($userId)
    {
        return $this->participants($userId)->firstOrFail();
    }

    /**
     * Restores all participants within a thread that has a new message.
     */
    public function activateAllParticipants()
    {
        $participants = $this->participants()->withTrashed()->get();
        foreach ($participants as $participant) {
            $participant->restore();
        }
    }

    /**
     * Generates a string of participant information.
     *
     * @param null $userId
     * @param string $column
     * @return string
     * @internal param string $columns
     *
     */
    public function participantsString($userId = null, $column = 'username')
    {
        if( isset($this->participantUsers) ) {
            $users = $this->participantUsers;

            if( !empty($userId) ){
                $users = $users->where('id', $userId);
            }
        }
        else
            $users = $this->participantUsers($userId);


        $userNames = $users->pluck($column)->all();

        return implode(', ', $userNames);
    }

    /**
     * Checks to see if a user is a current participant of the thread.
     *
     * @param $userId
     *
     * @return bool
     */
    public function hasParticipant($userId)
    {
        $participants = $this->participants()->where('user_id', '=', $userId);
        if ($participants->count() > 0) {
            return true;
        }

        return false;
    }

    /**
     * Returns array of unread messages in thread for given user.
     *
     * @param $userId
     *
     * @return \Illuminate\Support\Collection
     */
    public function userUnreadMessages($userId)
    {
        $messages = $this->messages()->get();
        $participant = $this->getParticipantFromUser($userId);
        if (!$participant) {
            return collect();
        }
        if (!$participant->last_read) {
            return collect($messages);
        }
        $unread = [];
        $i = count($messages) - 1;
        while ($i) {
            if ($messages[$i]->updated_at->gt($participant->last_read)) {
                array_push($unread, $messages[$i]);
            } else {
                break;
            }
            --$i;
        }

        return collect($unread);
    }

    /**
     * Returns count of unread messages in thread for given user.
     *
     * @param $userId
     *
     * @return int
     */
    public function userUnreadMessagesCount($userId)
    {
        return $this->userUnreadMessages($userId)->count();
    }
}
