<?php

namespace WebIM;

class Router {

	/*
	 * WebIM Ticket
	 */
	private $ticket;

	/*
	 * WebIM Client
	 */
	private $client;

	/*
	 * WebIM Plugin
	 */
	private $plugin;

	/*
	 * WebIM Model
	 */
	private $model;

	public function __construct() { }

    public function route() {

        global $IMC;

        if( !$this->plugin->logined() ) exit("Login Required");

		//IM Ticket
		$ticket = $this->input('ticket');
		if($ticket) $ticket = stripslashes($ticket);	
		$this->ticket = $ticket;

		//IM Client
        $this->client = new \WebIM\WebIM(
            $this->currentEndpoint(), 
            $IMC['domain'], 
            $IMC['apikey'], 
            $IMC['server'], 
            $this->ticket
        );
        $method = $this->input('action');
        if($method && method_exists($this, $method)) {
            call_user_func(array($this, $method));
        } else {
            header( "HTTP/1.0 400 Bad Request" );
            exit("No Action Found.");
        }
    }

    /**
     * Plugin Get/Set
     */
    public function plugin($plugin = null) {
        if (func_num_args() === 0) {
            return $this->plugin;
        }
        $this->plugin = $plugin; 
    }

    /**
     * Model Get/Set
     */
    public function model($model = null) {
        if (func_num_args() === 0) {
            return $model;
        }
        $this->model = $model;
    }


    /**
     * Current Ednpoint
     */
    private function currentEndpoint() {
        return $this->plugin->currentUser();
    }
    
    /**
     * Current UID
     */
    private function currentUID() {
        $ep = $this->currentEndpoint();
        return $ep['uid'];
    }

    /**
     * Boot Javascript
     */
	public function boot() {

        global $IMC;

        //FIX offline Bug
        $endpoint = $this->currentEndpoint();
        $endpoint['show'] = "unavailable";

		$fields = array(
			'theme', 
			'local', 
			'emot',
			'opacity',
			'enable_room', 
			'enable_chatlink', 
			'enable_shortcut',
			'enable_noti',
			'enable_menu',
			'show_unavailable',
			'upload');

		$scriptVar = array(
            'version' => WEBIM_VERSION,
			'product_name' => WEBIM_PRODUCT,
			'path' => WEBIM_PATH(),
			'is_login' => '1',
            'is_visitor' => $endpoint['role'] === 'visitor',
			'login_options' => '',
			'user' => $endpoint,
			'setting' => $this->model->setting($endpoint['uid']),
			'min' => WEBIM_DEBUG ? "" : ".min"
		);

		foreach($fields as $f) {
			$scriptVar[$f] = $IMC[$f];	
		}

		header("Content-type: application/javascript");
		header("Cache-Control: no-cache");
		echo "var _IMC = " . json_encode($scriptVar) . ";" . PHP_EOL;

		$script = <<<EOF
_IMC.script = window.webim ? '' : ('<link href="' + _IMC.path + 'static/webim' + _IMC.min + '.css?' + _IMC.version + '" media="all" type="text/css" rel="stylesheet"/><link href="' + _IMC.path + 'static/themes/' + _IMC.theme + '/jquery.ui.theme.css?' + _IMC.version + '" media="all" type="text/css" rel="stylesheet"/><script src="' + _IMC.path + 'static/webim' + _IMC.min + '.js?' + _IMC.version + '" type="text/javascript"></script><script src="' + _IMC.path + 'static/i18n/webim-' + _IMC.local + '.js?' + _IMC.version + '" type="text/javascript"></script>');
_IMC.script += '<script src="' + _IMC.path + 'static/webim.' + _IMC.product_name + '.js?vsn=' + _IMC.version + '" type="text/javascript"></script>';
document.write( _IMC.script );

EOF;
		exit($script);
	}

    /**
     * Endpoint Online
     */
	public function online() {
        global $IMC;
		$uid = $this->currentUID();
        $show = $this->input('show');

        //buddy, room, chatlink ids
		$chatlinkIds= $this->idsArray($this->input('chatlink_ids') );
		$activeRoomIds = $this->idsArray( $this->input('room_ids') );
		$activeBuddyIds = $this->idsArray( $this->input('buddy_ids') );
		//active buddy who send a offline message.
		$offlineMessages = $this->model->offlineHistories($uid);
		foreach($offlineMessages as $msg) {
			if(!in_array($msg['from'], $activeBuddyIds)) {
				$activeBuddyIds[] = $msg['from'];
			}
		}
        //buddies of uid
		$buddies = $this->plugin->buddies($uid);
        $buddyIds = array_map(function($buddy) { return $buddy['id']; }, $buddies);
        $buddyIdsWithoutInfo = array_filter( array_merge($chatlinkIds, $activeBuddyIds), function($id) use($buddyIds){ return !in_array($id, $buddyIds); } );
        //buddies by ids
		$buddiesByIds = $this->plugin->buddiesByIds($buddyIdsWithoutInfo);
        //all buddies
        $buddies = array_merge($buddies, $buddiesByIds);

        $rooms = array(); $roomIds = array();
		if( $IMC['enable_room'] ) {
            //persistent rooms
			$persistRooms = $this->plugin->rooms($uid);
            //temporary rooms
			$temporaryRooms = $this->model->rooms($uid);
            $rooms = array_merge($persistRooms, $temporaryRooms);
            $roomIds = array_map(function($room) { return $room['id']; }, $rooms);
		}

		//===============Online===============
		$data = $this->client->online($buddyIds, $roomIds, $show);
		if( $data->success ) {
            $rtBuddies = array();
            $presences = $data->presences;
            foreach($buddies as $buddy) {
                $id = $buddy['id'];
                if( isset($presences->$id) ) {
                    $buddy['presence'] = 'online';
                    $buddy['show'] = $presences->$id;
                } else {
                    $buddy['presence'] = 'offline';
                    $buddy['show'] = 'unavailable';
                }
                $rtBuddies[$id] = $buddy;
            }
			//histories for active buddies and rooms
			foreach($activeBuddyIds as $id) {
                if( isset($rtBuddies[$id]) ) {
                    $rtBuddies[$id]['history'] = $this->model->histories($uid, $id, "chat" );
                }
			}
            if( !$IMC['show_unavailable'] ) {
                $rtBuddies = array_filter($rtBuddies, 
                    function($buddy) { return $buddy['presence'] === 'online'; });        
            }
            $rtRooms = array();
            if( $IMC['enable_room'] ) {
                foreach($rooms as $room) {
                    $rtRooms[$room['id']] = $room;
                }
                foreach($activeRoomIds as $id){
                    if( isset($rtRooms[$id]) ) {
                        $rtRooms[$id]['history'] = $this->model->histories($uid, $id, "grpchat" );
                    }
                }
            }

			$this->model->offlineReaded($this->currentUID());

            $endpoint = $this->currentEndpoint();
            if($show) $endpoint['show'] = $show;

            $this->jsonReply(array(
                'success' => true,
                'connection' => $data->connection,
                'user' => $endpoint,
                'buddies' => array_values($rtBuddies),
                'rooms' => array_values($rtRooms),
                'new_messages' => $offlineMessages,
                'server_time' => microtime(true) * 1000
            ));
		} else {
			$this->jsonReply(array ( 
				'success' => false,
                'error' => $data
            )); 
        }
	}

    /**
     * Offline API
     */
	public function offline() {
		$this->client->offline();
		$this->okReply();
	}

    /**
     * Browser Refresh, may be called
     */
	public function refresh() {
		$this->client->offline();
		$this->okReply();
	}

    /**
     * Buddies by ids
     */
	public function buddies() {
		$ids = $this->input('ids');
		$this->jsonReply($this->plugin->buddiesByIds($ids));
	}

    /**
     * Send Message
     */
	public function message() {
        $endpoint = $this->currentEndpoint();
		$type = $this->input("type");
		$offline = $this->input("offline");
		$to = $this->input("to");
		$body = $this->input("body");
		$style = $this->input("style");
		$send = $offline == "true" || $offline == "1" ? 0 : 1;
		$timestamp = $this->microtimeFloat() * 1000;
		if( strpos($body, "webim-event:") !== 0 ) {
            $this->model->insertHistory(array(
				"send" => $send,
				"type" => $type,
				"to" => $to,
                'from' => $endpoint['id'],
                'nick' => $endpoint['nick'],
				"body" => $body,
				"style" => $style,
				"timestamp" => $timestamp,
			));
		}
		if($send == 1){
			$this->client->message(null, $to, $body, $type, $style, $timestamp);
		}
		$this->okReply();
	}

    /**
     * Update Presence
     */
	public function presence() {
		$show = $this->input('show');
		$status = $this->input('status');
		$this->client->presence($show, $status);
		$this->okReply();
	}

    /**
     * Send Status
     */
	public function status() {
		$to = $this->input("to");
		$show = $this->input("show");
		$this->client->status($to, $show);
		$this->okReply();
	}

    /**
     * Read History
     */
	public function history() {
		$uid = $this->currentUID();
		$with = $this->input('id');
		$type = $this->input('type');
		$histories = $this->model->histories($uid, $with, $type);
		$this->jsonReply($histories);
	}

    /**
     * Clear History
     */
	public function clear_history() {
		$id = $this->input('id');
		$this->model->clearHistories($this->currentUID(), $id);
		$this->okReply();
	}

    /**
     * Download History
     */
	public function download_history() {
		$uid = $this->currentUID();
		$id = $this->input('id');
		$type = $this->input('type');
		$histories = $this->model->histories($uid, $id, $type, 1000 );
		$date = date( 'Y-m-d' );
		if($this->input('date')) {
			$date = $this->input('date');
		}
		header('Content-Type',	'text/html; charset=utf-8');
		header('Content-Disposition: attachment; filename="histories-'.$date.'.html"');
		echo "<html><head>";
		echo "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" />";
		echo "</head><body>";
		echo "<h1>Histories($date)</h1>".PHP_EOL;
		echo "<table><thead><tr><td>用户</td><td>消息</td><td>时间</td></tr></thead><tbody>";
		foreach($histories as $history) {
			$nick = $history['nick'];
			$body = $history['body'];
			$style = $history['style'];
			$time = date( 'm-d H:i', (float)$history['timestamp']/1000 ); 
			echo "<tr><td>{$nick}</td><td style=\"{$style}\">{$body}</td><td>{$time}</td></tr>";
		}
		echo "</tbody></table>";
		echo "</body></html>";
	}

    /**
     * Get Rooms
     */
	public function rooms() {
		$ids = $this->input("ids");
        $ids = explode(',', $ids);
		$this->jsonReply($this->plugin->roomsByIds($ids));	
	}

    /**
     * Invite Room
     */
    public function invite() {
        $uid = $this->currentUID();
        $endpoint = $this->currentEndpoint();
        $roomId = $this->input('id');
        $nick = $this->input('nick');
        if(strlen($nick) === 0) {
			header("HTTP/1.0 400 Bad Request");
			exit("Nick is Null");
        }
        //find persist room 
        $room = $this->plugin->room($roomId);
        if(!$room) {
            //create temporary room
            $room = $this->model->createRoom(array(
                'owner' => $uid,
                'name' => $roomId, 
                'nick' => $nick
            ));
        }
        //join the room
        $this->model->joinRoom($roomId, $uid, $endpoint['nick']);
        //invite members
        $members = explode(",", $this->input('members'));
        $members = $this->plugin->buddiesByIds($members);
        $this->model->inviteRoom($roomId, $members);
        //send invite message to members
        foreach($members as $m) {
            $body = "webim-event:invite|,|{$roomId}|,|{$nick}";
            $this->client->message(null, $m['id'], $body); 
        }
        //tell server that I joined
        $this->client->join($roomId);
        $this->jsonReply(array(
            'id' => $room['name'],
            'nick' => $room['nick'],
            'temporary' => true,
            'pic_url' => WEBIM_IMAGE('room.png')
        ));
    }

    /**
     * Join Room
     */
	public function join() {
        $uid = $this->currentUID();
        $endpoint = $this->currentEndpoint();
        $roomId = $this->input('id');
        $nick = $this->input('nick');
        $room = $this->plugin->room($roomId);
        if(!$room) {
            $room = $this->model->room($roomId);
        }
        if($room) {
            $this->model->joinRoom($roomId, $uid, $endpoint['nick']);
            $data = $this->client->join($roomId);
            $this->jsonReply(array(
                'id' => $roomId,
                'nick' => $nick,
                'temporary' => true,
                'pic_url' => WEBIM_IMAGE('room.png')
            ));
        } else {
			header("HTTP/1.0 404 Not Found");
			exit("Can't found room: {$roomId}");
        }
	}

    /**
     * Leave Room
     */
	public function leave() {
        $uid = $this->currentUID();
		$room = $this->input('id');
		$this->client->leave( $room );
        $this->model->leaveRoom($room, $uid);
		$this->okReply();
	}

    /**
     * Room members
     */
	public function members() {
        $endpoint = $this->currentEndpoint();
        $roomId = $this->input('id');
        $room = $this->plugin->room($roomId);
        $members = array();
        if($room) {
            $members = $this->plugin->members($roomId);
        } else {
            $room = $this->model->room($roomId);
            if($room) {
                $members = $this->model->members($roomId);
            }
        }
        if(!$room) {
			header("HTTP/1.0 404 Not Found");
			exit("Can't found room: {$roomId}");
            return;
        }
        $presences = (array)$this->client->members($roomId);
        $rtMembers = array();
        foreach($members as $m) {
            $id = $m['id'];
            if(isset($presences[$id])) {
                $m['presence'] = 'online';
                $m['show'] = $presences[$id];
            } else {
                $m['presence'] = 'offline';
                $m['show'] = 'unavailable';
            }
            $rtMembers[] = $m;
        }
        $this->jsonReply($rtMembers);
	}

    /**
     * Block Room
     */
    public function block() {
        $uid = $this->currentUID();
        $room = $this->input('id');
        $this->model->blockRoom($room, $uid);
        $this->okReply();
    }

    /**
     * Unblock Room
     */
    public function unblock() {
        $uid = $this->currentUID();
        $room = $this->input('id');
        $this->model->unblockRoom($room, $uid);
        $this->okReply();
    }
    
    /**
     * Read Notifications
     */
	public function notifications() {
        $uid = $this->currentUID();
		$notifications = $this->plugin->notifications($uid);
		$this->jsonReply($notifications);
	}

    /**
     * Setting
     */
    public function setting() {
        $data = $this->input('data');
		$this->model->setting($this->currentUID(), $data);
		$this->okReply();
    }

	private function input($name, $default = null) {
		if( isset( $_POST[$name] ) ) return $_POST[$name];
		if( isset( $_GET[$name] ) ) return $_GET[$name]; 
		return $default;
	}

	private function okReply() {
		$this->jsonReply('ok');
	}

	private function jsonReply($data) {
		header('Content-Type:application/json; charset=utf-8');
		exit(json_encode($data));
	}

	private function idsArray( $ids ){
		return ($ids===null || $ids==="") ? array() : (is_array($ids) ? array_unique($ids) : array_unique(explode(",", $ids)));
	}

	private function microtimeFloat() {
		list($usec, $sec) = explode(" ", microtime());
		return ((float)$usec + (float)$sec);
	}

}

