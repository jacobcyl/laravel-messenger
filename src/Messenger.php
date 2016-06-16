<?php
namespace Jacobcyl\Messenger;


use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Jacobcyl\Messenger\Events\PushNotification;
use Illuminate\Support\Collection;
use Jacobcyl\Messenger\Exceptions\IncorrectUsageException;
use Jacobcyl\Messenger\Exceptions\InvalidArgumentException;
use Jacobcyl\Messenger\Models\Message;
use Jacobcyl\Messenger\Models\Thread;
use Auth;
use Event;

class Messenger
{
    /**
     * Attributes.
     *
     * @var array
     */
    protected $message = [
        'subject' => '',
        'body' => '',
        'link' => null,
        'links' => null,
        'senderId' => '',
        'createdAt' => '',
        'store' => 1
    ];

    protected $toUsers;

    protected $toAll = false;

    /**
     * Message backup.
     *
     * @var array
     */
    protected $messageBackup;

    /**
     * @var bool . store message to the database?
     */
    protected $isStore = true;

    /**
     * @var bool notify user ?
     */
    protected $isNotify = false;

    /**
     * MessageManager constructor.
     */
    public function __construct()
    {
        $this->messageBackup = $this->message;
    }

    // fire the message-created event
    private function pushNotification(){
        if($this->toAll){
            Event::fire(new PushNotification(null, $this->message, true));
        }else if(count($this->toUsers)){
            foreach($this->toUsers as $user)
                Event::fire(new PushNotification($user, $this->message, false));
        }
    }

    private function createMessage(){
        $thread = Thread::create([
            'subject'   => $this->message['subject'],
            'to_all'    => $this->toAll ? 1 : 0 ,
        ]);

        Message::create([
            'thread_id' => $thread->id,
            'user_id'   => $this->message['senderId'],
            'body'      => $this->message['body']
        ]);

        // participants
        if(!$this->toAll && count($this->toUsers)){
            //$toUsers = new Collection($this->toUsers);
            $thread->addParticipants($this->toUsers->pluck('id')->all());
        }
    }

    /**
     * Send a message.
     *
     * @param $data
     *
     * @return mixed
     *
     * @throws InvalidArgumentException
     * @throws IncorrectUsageException
     */
    public function send($data = [])
    {
        if(!$this->isStore && !$this->isNotify){
            throw new IncorrectUsageException('please use at least one optional function between store() and notify()!');
        }

        $params = array_merge([
            'subject' => '',
            'body' => '',
            'link' => '',
            'links' => '',
            'senderId' => '',
            'createdAt' => Carbon::now(),
            'store' => ''
        ], $data);

        $required = ['subject', 'body', 'senderId'];

        foreach ($params as $key => $value) {
            if (in_array($key, $required, true) && empty($value) && empty($this->message[$key])) {
                throw new InvalidArgumentException("Attribute '$key' can not be empty!");
            }

            $params[$key] = empty($value) ? $this->message[$key] : $value;
        }

        //self::sendMsg($this->toUserIds, $this->message, $this->toAll, $this->isSave, $this->isNotify);
        if($this->isStore){
            self::createMessage();
        }

        if($this->isNotify){
            self::pushNotification();
        }
    }

    // create a new message
    public function create(array $message){
        $this->message = $message;
        return $this;
    }

    /**
     * set to notify user or not
     * @param bool $isNotify
     * @return $this
     */
    public function notify($isNotify = true){
        $this->isNotify = $isNotify;
        return $this;
    }

    // store the message to the database
    public function store(){
        $this->isStore = true;
        $this->message['store'] = 1;
        return $this;
    }

    // do not store the message
    public function unStore(){
        $this->isStore = false;
        $this->message['store'] = 0;
        return $this;
    }

    /**
     * set message recipients
     * @param Collection $toUsers
     * @return $this
     * @throws InvalidArgumentException
     */
    public function to(Collection $toUsers){
        if(empty($toUsers))
            throw new InvalidArgumentException("Target user must be set! Or use toAll() function to send to all users");

        $this->toUsers = $toUsers;
        $this->toAll = false;

        return $this;
    }

    /**
     * send message to all of the user
     * @return $this
     */
    public function toAll(){
        $this->toUsers = null;
        $this->toAll = true;

        return $this;
    }

    /**
     * Magic access..
     *
     * @param string $method
     * @param array  $args
     *
     * @return Messenger
     */
    public function __call($method, $args)
    {
        $map = [
            'subject' => 'subject',
            'body' => 'body',
            'link' => 'link',
            'links' => 'links',
            'sender' => 'senderId',
            'url' => 'link',
        ];

        if (0 === stripos($method, 'with')) {
            $method = lcfirst(substr($method, 4));
        }

        if (0 === stripos($method, 'and')) {
            $method = lcfirst(substr($method, 3));
        }

        if (isset($map[$method])) {
            $this->message[$map[$method]] = array_shift($args);
        }

        return $this;
    }
}