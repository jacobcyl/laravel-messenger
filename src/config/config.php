<?php

return [

    'user_model' => 'App\Models\User',

    /**
     * this token is used to generate new token to socket.io room
     */
    'token' => 'SomeRandomString',

    /**
     * redis sub/pub channel name which should be broadcast on
     */
    'redis_channel' =>'notification',
];
