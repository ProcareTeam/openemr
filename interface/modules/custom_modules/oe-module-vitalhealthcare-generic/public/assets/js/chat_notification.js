
setScriptToHead('https://cdnjs.cloudflare.com/ajax/libs/toastr.js/2.0.1/css/toastr.css', 'style');
setScriptToHead('https://cdnjs.cloudflare.com/ajax/libs/toastr.js/2.0.1/js/toastr.js', 'script');
setScriptToHead('https://cdnjs.cloudflare.com/ajax/libs/howler/2.2.4/howler.min.js', 'script');

var wsUpdateStatus = null;
var wsChatNotification = null;

function playNotificationSound() {
	const sound_source = top.webroot_url + "/interface/modules/custom_modules/oe-module-vitalhealthcare-generic/public/assets/sound/simplenotification.mp3";
    if(sound_source != "") {
      var sound = new Howl({
        src: [sound_source]
      });
      sound.play();
    }
}

function setScriptToHead(url, type = 'script') {
	if(type == 'script') {
		var script = document.createElement('script');
		script.type = 'text/javascript';
		script.src = url;    
	} else if(type == 'style') {
		var script = document.createElement('link');
		script.rel = 'stylesheet';
		script.href = url;
	}

	document.head.appendChild(script);
}

function pushNotification(title, message, convid, notificationsoundpath = false) {
	toastr.success(message, title, {
	    "progressBar": true,
	    "timeOut": 10000,
	    "closeButton": true,
	    "positionClass": "toast-bottom-right",
	    "onclick": function() {
	        viewChatBoard(convid); // Call your JavaScript function here
	    },
		"onShown": function() {
			if(notificationsoundpath === true) {
				playNotificationSound();
			}
		},
	    "preventDuplicates": false,
	    "showDuration": 1000,
	    "hideDuration": 1000,
	    "showEasing": "swing",
	    "hideEasing": "linear",
	    "showMethod": "fadeIn",
	    "hideMethod": "fadeOut",
	    "tapToDismiss": true,
	    "target": "body",
	    "cssClass": "custom-toast"
	});
}

function viewChatBoard(convid = '') {
    top.navigateTab(top.webroot_url + '/interface/main/messages/chat_board.php?convid1='+convid, "chatboard", function () {
        top.activateTabByName("chatboard",true);
    });
}

async function fetchNewChat() {
	var data = [];
    data.push({ name: "ajax_action", value: "fetch_new_chat" });

    return await $.ajax({
        url: top.webroot_url + '/interface/main/messages/chat_board.php',
        method: "POST",
        data: $.param(data),
        success: function(result) {
            let dataJSON = JSON.parse(result);

            if(dataJSON.hasOwnProperty('items') && dataJSON['items'].hasOwnProperty('n')) {
            	$('.chatboard .badge-primary').text(Object.keys(dataJSON['items']['n']).length);
        	} else {
        		$('.chatboard .badge-primary').text(0);
        	}
        },                      
    });
}

function webConnect() {

	closeChatNotification();

	// WEB Socket
	wsChatNotification = new WebSocket(`${vh_websocket_address_type}://${vh_websocket_host}:${vh_websocket_port}/chat_notification/${vh_websocket_siteurl}`); 

	// Message received from server
	wsChatNotification.onmessage = async function(ev) {
	    const msgData = JSON.parse(ev['data']);

	    if(msgData.hasOwnProperty('data') && msgData['data'].hasOwnProperty('action') && ["new-message", "agent-new-message", "status-update"].includes(msgData['data']['action'])) {
	    	
	    	if(["new-message"].includes(msgData['data']['action']) && msgData['data'].hasOwnProperty('webhook') && msgData['data']['webhook'].hasOwnProperty('data')) {
		    	let mData = msgData['data']['webhook']['data'][0];
		    	let mMessage = mData['details']['message'];

		    	pushNotification('New Message', mMessage, msgData['data']['conversation_id'], true);
	    	}

	    	fetchNewChat();
	    }
	}

	wsChatNotification.onclose = function(e) {
	    console.log('Socket is closed. Reconnect will be attempted in 1 second.', e.reason);
	    
	    if(vh_chat_notification === true) {
		    setTimeout(function() {
		      webConnect();
		    }, 2000);
		}
	}

	wsChatNotification.onerror = function(err) {
	    console.error('Socket encountered error: ', err.message, 'Closing socket');
	    wsChatNotification.close();
	}

	return wsChatNotification;
}

function closeChatNotification() {
	if(wsChatNotification && wsChatNotification != null) {
		wsChatNotification.close();
		wsChatNotification = null;
	}
}

function triggerChatNotification() {
	if(vh_chat_notification === true && wsChatNotification == null) {
 		$('#chatCountContainer').html('<a class="btn btn-secondary btn-sm chatboard" href="#" onclick="viewChatBoard()"><i class="fa fa-comments"></i>&nbsp;<span class="badge badge-primary" style="display:inline">0</span></a>');

		pushNotification("Chat Notification", "Click here to Open Chat Configuration", "", true);
		fetchNewChat();

	 	webConnect();
 	} else if(vh_chat_notification === false) {
 		closeChatNotification();
 	}
}


function webConnectUpdateStatus() {
	closeUpdateStatusConnection();

	wsUpdateStatus = new WebSocket(`${vh_websocket_address_type}://${vh_websocket_host}:${vh_websocket_port}/update_status/${vh_websocket_siteurl}`);

    wsUpdateStatus.onmessage = async function(ev) {
    }

    wsUpdateStatus.onopen = function(e) {
        wsUpdateStatus.updateInterval = setInterval(function () {
            if(wsUpdateStatus && wsUpdateStatus != null) {
            	wsUpdateStatus.send(JSON.stringify({ "type" : "update_status", "data" : { "status" : 1 }} ));
        	}
        }, 10000);
    }

    wsUpdateStatus.onclose = function(e) {
	    console.log('Update Status Socket is closed. Reconnect will be attempted in 1 second.', e.reason);
	    
	    if(vh_user_status_update === true) {
		    setTimeout(function() {
		      webConnectUpdateStatus();
		    }, 2000);
		}
	}

	wsUpdateStatus.onerror = function(err) {
	    console.error('Socket encountered error: ', err.message, 'Closing socket');
	    wsUpdateStatus.close();
	}

    return wsUpdateStatus;
}

function closeUpdateStatusConnection() {
	if(wsUpdateStatus && wsUpdateStatus != null) {
		wsUpdateStatus.close();
		clearInterval(wsUpdateStatus.updateInterval);
		wsUpdateStatus = null;
	}
}

function triggerUpdateStatus() {
	if(vh_user_status_update === true && wsUpdateStatus == null) {
 		webConnectUpdateStatus();
 	} else if(vh_user_status_update === false) {
 		closeUpdateStatusConnection();
 	}
}

window.document.addEventListener("chat-update", function(event) {
	if(event && event['detail']) {
		if(event.detail.status == "online") {
			vh_user_status_update = true;
		} else if(event.detail.status == "offline"){
			vh_user_status_update = false;
		}

		if(event.detail.notification && event.detail.notification != "") {
			const uN = event.detail.notification.split(", ");

			if(uN.includes("CHAT")) {
				vh_chat_notification = true;
			} else {
				vh_chat_notification = false;
			}
		}

		triggerChatNotification();
		triggerUpdateStatus();
	}
});

window.onload = function(e) {
	triggerChatNotification();
 	triggerUpdateStatus();
}