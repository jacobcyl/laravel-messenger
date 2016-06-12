<?php

namespace Jacobcyl\Messenger\Traits;

use Jacobcyl\Messenger\Models\Message;
use Jacobcyl\Messenger\Models\Participant;
use Jacobcyl\Messenger\Models\Thread;
use DB;
use Cache;

trait Messagable
{
    /**
     * Message relationship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function messages()
    {
        return $this->hasMany('Jacobcyl\Messenger\Models\Message');
    }

    /**
     * Participants relationship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function participants()
    {
        return $this->hasMany('Jacobcyl\Messenger\Models\Participant');
    }

    public function loadBroadcastThreads(){
        $latestId = $this->getBroadcastLatestId();
        $newBroadcastThreads = Thread::where('to_all', 1)->where('id', '>', $latestId)->latest()->get();

        $participants = [];
        foreach ( $newBroadcastThreads as $thread ){
            $participant = [
                'thread_id' => $thread->id,
                'user_id'   => $this->id,
                'last_read' => null,
                'created_at'=> $thread->created_at,
                'updated_at'=> $thread->created_at,
            ];
            $participants[] = $participant;
        }
        if(count($participants)) {
            DB::table('participants')->insert($participants);

            $this->setBroadcastLatestId($participants[0]['thread_id']);
        }
    }

    /**
     * get user`s latest broadcasted message thread id
     * @param $userId
     * @return mixed
     */
    private function getBroadcastLatestId(){
        return Cache::rememberForever('messenger:latestid:user:'.$this->id, function (){
            
            $latestThread = $this->threads()->where('to_all', 1)->latest()->first();
            if(!$latestThread){
                $latestThread = Thread::where('to_all', 1)->where('created_at', '<', $this->created_at)->latest()->first();
            }
            return $latestThread ? $latestThread->id : 0;
        });
    }

    private function setBroadcastLatestId($latestId){
        Cache::forever('messenger:latestid:user:'.$this->id, $latestId);
    }

    /**
     * Thread relationship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\belongsToMany
     */
    public function threads()
    {

        return $this->belongsToMany(
            'Jacobcyl\Messenger\Models\Thread',
            'participants'
        )->with('messages', 'participantUsers')->withPivot('last_read')->latest('threads.created_at');
    }

    /**
     * Unread threads as a relationship
     *
     * @return \Illuminate\Database\Eloquent\Relations\belongsToMany
     */
    public function unreadThreads()
    {
        return $this->threads()->where(function ($query){
            $query->whereRaw('lt_participants.last_read IS NULL OR lt_threads.updated_at > lt_participants.last_read');
        });
    }

    /**
     * Returns the new messages count for user.
     *
     * @return int
     */
    public function newThreadsCount()
    {
        return $this->unreadThreads()->count();
    }

    /**
     * Returns the id of all threads with new messages.
     *
     * @return array
     */
    public function threadsWithNewMessages()
    {
        return $this->unreadThreads()->lists('id');
    }
}
