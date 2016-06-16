var app = require('express');
var http = require('http').Server(app);
var io = require('socket.io')(http);
var Redis = require('ioredis');
var redis = new Redis(); // if you set password , you can pass {password: 'password'}, please see ioredis docs
var users = [];

// subscribe redis channel. Attention the channel name must be the same as Jacobcyl\Messenger\laravel-messenger config  channel
redis.subscribe('notification', function(err, count) {
    console.log('connect! '+count);
});

var msgIo = io.of('/msg').on('connection', function(socket) {
    socket.userIndex = users.length;

    // Log in the specified room
    socket.on('login', function(token) {
        socket.join('room:'+token);
        users.push(token);
    });

    socket.on('disconnect', function(){
        users.splice(socket.userIndex, 1);
    });
});

// get redis publish message
redis.on('message', function(channel, notification) {
    console.log(notification);
    notification = JSON.parse(notification);

    // send to users | all terminals
    if(notification.data.toAll)
        msgIo.volatile.emit('notification', notification.data.message);
    else
        msgIo.volatile.to('room:'+notification.data.token).emit('notification', notification.data.message);
});

// listen port 3000
http.listen(3000, function() {
    console.log('Listening on Port 3000');
});
