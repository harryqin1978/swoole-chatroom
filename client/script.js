$( document ).ready(function() {
    console.log( "ready!" );

    console.log(JSON.parse('{"foo" : 1, "bar": "234"}'));

    var wsServer = 'ws://192.168.56.101:9601';
    var websocket = new WebSocket(wsServer);
    websocket.onopen = function (evt) {
        console.log("Connected to WebSocket server.");
    };

    websocket.onclose = function (evt) {
        addMessage("Disconnected");
    };

    websocket.onmessage = function (evt) {
        console.log(evt.data);
        var evt_data = JSON.parse(evt.data);
        addMessage(evt_data.message);
    };

    websocket.onerror = function (evt, e) {
        console.log('Error occured: ' + evt.data);
    };

    $( "#chat-form" ).on( "submit", function(e) {
        e.preventDefault();
        var message = $('#myname').val() + ': ' + $('#message').val();
        var data = {'message' : message};
        data = JSON.stringify(data);
        websocket.send(data);
        clearMessage();
    });
});

function addMessage(message) {
    $( '#mbox' ).append( '<p>' + message + '</p>' );
    $( 'body' ).scrollTop( $('body')[0].scrollHeight );
}

function clearMessage() {
    $( '#message' ).val('');
}