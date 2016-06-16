<?php

namespace Jacobcyl\Messenger\Events;

use App\Events\Event;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Support\Facades\Log;

/**
 * Class PushNotification
 * @package App\Events
 */
class PushNotification extends Event implements ShouldBroadcast
{
    use SerializesModels;

    /**
     * @var
     */
    public $token;

    /**
     * @var
     */
    public $message;

    /**
     * determine to send to all  users or not
     * default is false
     * @var boolean
     */
    public $toAll;
    public $user;

    /**
     * Create a new event instance.
     *
     * @param User $user
     * @param $message
     */
    public function __construct($user, $message, $toAll = false)
    {
        if(!empty($user)){
            $this->user = $user;
            $this->token = sha1($user->id + config('messenger.token'));
        }
        $this->message = $message;
        $this->toAll = $toAll;
    }

    /**
     * Get the channels the event should be broadcast on.
     *
     * @return array
     */
    public function broadcastOn()
    {
        return [config('messenger.redis_channel', 'notification')];
    }
}
