<?php

/**
 * WebIM-for-PHP5 
 *
 * @author      Ery Lee <ery.lee@gmail.com>
 * @copyright   2014 NexTalk.IM
 * @link        http://github.com/webim/webim-for-php5
 * @license     MIT LICENSE
 * @version     5.4.1
 * @package     WebIM
 *
 * MIT LICENSE
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to
 * the following conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
 * LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
 * OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
 * WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */
namespace WebIM;

/**
 * WebIM Router
 *
 * @package WebIM
 * @autho Ery Lee
 * @since 5.4.1
 */
class Router {

    /**
     * Current user
     */
    private $user = null;

	/*
	 * WebIM Model
	 */
	private $model;

	/*
	 * WebIM Plugin
	 */
	private $plugin;

	/*
	 * WebIM Client
	 */
	private $client;

    public function __construct() { 
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
     * Route and dispatch ajax request
     */
    public function route() {

        global $IMC;

        $user = $this->plugin->user();
        if($user == null &&  $IMC['visitor']) {
            $user = $this->model->visitor();
        }

        if(!$user) exit(json_encode("Login Required"));

        //WebIM User
        $this->user = $user;

		//WebIM Ticket
		$ticket = $this->input('ticket');
		if($ticket) $ticket = stripslashes($ticket);	

		//IM Client
        $this->client = new \WebIM\Client(
            $this->user, 
            $IMC['domain'], 
            $IMC['apikey'], 
            $IMC['server'], 
            $ticket
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
     * Boot Javascript
     */
	public function boot() {

        global $IMC;
		$fields = array(
            'version',
			'theme', 
			'local', 
			'emot',
			'opacity',
            'discussion',
			'enable_room', 
			'enable_chatlink', 
			'enable_shortcut',
			'enable_noti',
			'enable_menu',
			'show_unavailable',
			'upload');

        $this->user->show = "unavailable";
        $uid = $this->user->id;
        $webim_path = WEBIM_PATH();
        /*
        if( substr($webim_path, strlen($webim_path)-1) !== '/' ) {
            $webim_path .= '/';
        }
        */

		$scriptVar = array(
            'version' => WEBIM_VERSION,
			'product' => WEBIM_PRODUCT,
			'path' => $webim_path,
			'is_login' => '1',
            'is_visitor' => $this->isvid($uid),
			'login_options' => '',
			'user' => $this->user,
			'setting' => $this->model->setting($uid),
            'jsonp' => false,
			'min' => WEBIM_DEBUG ? "" : ".min"
		);

		foreach($fields as $f) { $scriptVar[$f] = $IMC[$f];	}

		header("Content-type: application/javascript");
		header("Cache-Control: no-cache");
		echo "var _IMC = " . json_encode($scriptVar) . ";" . PHP_EOL;

		$script = <<<EOF
_IMC.script = window.webim ? '' : ('<link href="' + _IMC.path + 'static/webim' + _IMC.min + '.css?' + _IMC.version + '" media="all" type="text/css" rel="stylesheet"/><link href="' + _IMC.path + 'static/themes/' + _IMC.theme + '/jquery.ui.theme.css?' + _IMC.version + '" media="all" type="text/css" rel="stylesheet"/><script src="' + _IMC.path + 'static/webim' + _IMC.min + '.js?' + _IMC.version + '" type="text/javascript"></script><script src="' + _IMC.path + 'static/i18n/webim-' + _IMC.local + '.js?' + _IMC.version + '" type="text/javascript"></script>');
_IMC.script += '<script src="' + _IMC.path + 'static/webim.' + _IMC.product + '.js?vsn=' + _IMC.version + '" type="text/javascript"></script>';
document.write( _IMC.script );

EOF;
		exit($script);
	}

    /**
     * Online
     */
	public function online() {
        global $IMC;

        $uid = $this->user->id;
        $show = $this->input('show');

        //buddy, room, chatlink ids
		$chatlinkIds= $this->idsArray($this->input('chatlink_ids', '') );
		$activeRoomIds = $this->idsArray( $this->input('room_ids') );
		$activeBuddyIds = $this->idsArray( $this->input('buddy_ids') );

		//active buddy who send a offline message.
		$offlineMessages = $this->model->offlineHistories($uid);
		foreach($offlineMessages as $msg) {
			if(!in_array($msg->from, $activeBuddyIds)) {
				$activeBuddyIds[] = $msg->from;
			}
		}
        //buddies of uid
		$buddies = $this->plugin->buddies($uid);
        $buddyIds = array_map(array($this, 'buddyId'), $buddies);
        $buddyIdsWithoutInfo = array();
        foreach(array_merge($chatlinkIds, $activeBuddyIds) as $id) {
            if( !in_array($id, $buddyIds) ) {
                $buddyIdsWithoutInfo[] = $id;
            }
        }
        //buddies by ids
		$buddiesByIds = $this->plugin->buddiesByIds($uid, $buddyIdsWithoutInfo);

        //all buddies
        $buddies = array_merge($buddies, $buddiesByIds);
        $allBuddyIds = array();
        foreach($buddies as $buddy) { $allBuddyIds[] = $buddy->id; }

        $rooms = array(); $roomIds = array();
		if( $IMC['enable_room'] ) {
            //persistent rooms
			$persistRooms = $this->plugin->rooms($uid);
            //temporary rooms
			$temporaryRooms = $this->model->rooms($uid);
            $rooms = array_merge($persistRooms, $temporaryRooms);
            $roomIds = array_map(array($this, 'roomId'), $rooms);
		}

		//===============Online===============
		$data = $this->client->online($allBuddyIds, $roomIds, $show);
		if( $data->success ) {
            $rtBuddies = array();
            $presences = $data->presences;
            foreach($buddies as $buddy) {
                $id = $buddy->id;
                if( isset($presences->$id) ) {
                    $buddy->presence = 'online';
                    $buddy->show = $presences->$id;
                } else {
                    $buddy->presence = 'offline';
                    $buddy->show = 'unavailable';
                }
                $rtBuddies[$id] = $buddy;
            }
			//histories for active buddies and rooms
			foreach($activeBuddyIds as $id) {
                if( isset($rtBuddies[$id]) ) {
                    $rtBuddies[$id]->history = $this->model->histories($uid, $id, "chat" );
                }
			}
            if( !$IMC['show_unavailable'] ) {
                $olBuddies = array();
                foreach($rtBuddies as $buddy) {
                    if($buddy->presence === 'online') $olBuddies[] = $buddy;
                }
                $rtBuddies = $olBuddies;
            }
            $rtRooms = array();
            if( $IMC['enable_room'] ) {
                foreach($rooms as $room) {
                    $rtRooms[$room->id] = $room;
                }
                foreach($activeRoomIds as $id){
                    if( isset($rtRooms[$id]) ) {
                        $rtRooms[$id]->history = $this->model->histories($uid, $id, "grpchat" );
                    }
                }
            }

			$this->model->offlineReaded($uid);

            if($show) $this->user->show = $show;

            $this->jsonReply(array(
                'success' => true,
                'connection' => $data->connection,
                'user' => $this->user,
                'presences' => $data->presences,
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
     * Offline
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
        $uid = $this->user->id;
		$ids = $this->input('ids');
        $vids = array();
        $uids = array();
        foreach(explode(',', $ids) as $id) {
            if($this->isvid($id)) { 
                $vids[] = $id;
            } else {
                $uids[] = $id;
            }
        }
        $buddies = array_merge(
            $this->plugin->buddiesByIds($uid, $uids),
            $this->model->visitors($vids)
        );
        $buddyIds = array_map(array($this, 'buddyId'), $buddies);
        $presences = $this->client->presences($buddyIds);
        foreach($buddies as $buddy) {
            $id = $buddy->id;
            if( isset($presences->$id) ) {
                $buddy->presence = 'online';
                $buddy->show = $presences->$id;
            } else {
                $buddy->presence = 'offline';
                $buddy->show = 'unavailable';
            }
        }
		$this->jsonReply($buddies);
	}

    /**
     * Send Message
     */
	public function message() {
		$type = $this->input("type");
		$offline = $this->input("offline");
		$to = $this->input("to");
		$body = stripslashes( $this->input("body") );
        if( defined('WEBIM_MESSAGE_DECODE') ) {
            $body = html_entity_decode($body);
        }
		$style = $this->input("style");
		$send = $offline == "true" || $offline == "1" ? 0 : 1;
		$timestamp = microtime(true) * 1000;
		if( strpos($body, "webim-event:") !== 0 ) {
            $this->model->insertHistory(array(
				"send" => $send,
				"type" => $type,
				"to" => $to,
                'from' => $this->user->id,
                'nick' => $this->user->nick,
				"body" => $body,
				"style" => $style,
				"timestamp" => $timestamp,
			));
		}
		if($send == 1){
			$this->client->message(null, $to, $body, $type, $style, $timestamp);
		}
        //Error Reply
        //$this->jsonReply(array('status' => 'error', 'message' => $body));
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
        $uid = $this->user->id;
		$with = $this->input('id');
		$type = $this->input('type');
		$histories = $this->model->histories($uid, $with, $type);
		$this->jsonReply($histories);
	}

    /**
     * Clear History
     */
	public function clear_history() {
        $uid = $this->user->id;
		$id = $this->input('id');
		$this->model->clearHistories($uid, $id);
		$this->okReply();
	}

    /**
     * Download History
     */
	public function download_history() {
        $uid = $this->user->id;
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
			$nick = $history->nick;
			$body = $history->body;
			$style = $history->style;
			$time = date( 'm-d H:i', (float)$history->timestamp/1000 ); 
			echo "<tr><td>{$nick}:</td><td style=\"{$style}\">{$body}</td><td>{$time}</td></tr>";
		}
		echo "</tbody></table>";
		echo "</body></html>";
	}

    /**
     * Get rooms
     */
	public function rooms() {
        $uid = $this->user->id;
		$ids = $this->input("ids");
        $ids = explode(',', $ids);
        $persistRooms = $this->plugin->roomsByIds($uid, $ids);
        $temporaryRooms = $this->model->roomsByIds($uid, $ids);
		$this->jsonReply(array_merge($persistRooms, $temporaryRooms));	
	}

    /**
     * Invite room
     */
    public function invite() {
        $uid = $this->user->id;
        $roomId = $this->input('id');
        $nick = $this->input('nick');
        if(strlen($nick) === 0) {
			header("HTTP/1.0 400 Bad Request");
			exit("Nick is Null");
        }
        //find persist room 
        $room = $this->findRoom($this->model, $roomId);
        if(!$room) {
            //create temporary room
            $room = $this->model->createRoom(array(
                'owner' => $uid,
                'name' => $roomId, 
                'nick' => $nick
            ));
        }
        //join the room
        $this->model->joinRoom($roomId, $uid, $this->user->nick);
        //invite members
        $members = explode(",", $this->input('members'));
        $members = $this->plugin->buddiesByIds($uid, $members);
        $this->model->inviteRoom($roomId, $members);
        //send invite message to members
        foreach($members as $m) {
            $body = "webim-event:invite|,|{$roomId}|,|{$nick}";
            $this->client->message(null, $m->id, $body); 
        }
        //tell server that I joined
        $this->client->join($roomId);
        $this->jsonReply(array(
            'id' => $room->name,
            'nick' => $room->nick,
            'temporary' => true,
            'pic_url' => WEBIM_IMAGE('room.png')
        ));
    }

    /**
     * Join room
     */
	public function join() {
        $uid = $this->user->uid;
        $roomId = $this->input('id');
        $nick = $this->input('nick');
        $room = $this->findRoom($this->plugin, $roomId);
        if(!$room) {
            $room = $this->findRoom($this->model, $roomId);
        }
        if(!$room) {
			header("HTTP/1.0 404 Not Found");
			exit("Can't found room: {$roomId}");
        }
        $this->model->joinRoom($roomId, $uid, $this->user->nick);
        $data = $this->client->join($roomId);
        $this->jsonReply(array(
            'id' => $roomId,
            'nick' => $nick,
            'temporary' => true,
            'pic_url' => WEBIM_IMAGE('room.png')
        ));
	}

    /**
     * Leave room
     */
	public function leave() {
        $uid = $this->user->id;
		$room = $this->input('id');
		$this->client->leave( $room );
        $this->model->leaveRoom($room, $uid);
		$this->okReply();
	}

    /**
     * Room members
     */
	public function members() {
        $members = array();
        $roomId = $this->input('id');
        $room = $this->findRoom($this->plugin, $roomId);
        if($room) {
            $members = $this->plugin->members($roomId);
        } else {
            $room = $this->findRoom($this->model, $roomId);
            if($room) {
                $members = $this->model->members($roomId);
            }
        }
        if(!$room) {
			header("HTTP/1.0 404 Not Found");
			exit("Can't found room: {$roomId}");
        }
        $presences = $this->client->members($roomId);
        $rtMembers = array();
        foreach($members as $m) {
            $id = $m->id;
            if(isset($presences->$id)) {
                $m->presence = 'online';
                $m->show = $presences->$id;
            } else {
                $m->presence = 'offline';
                $m->show = 'unavailable';
            }
            $rtMembers[] = $m;
        }
        usort($rtMembers, function($m1, $m2) {
            if($m1->presence === $m2->presence) return 0;
            if($m1->presence === 'online') return 1;
            return -1;
        });
        $this->jsonReply($rtMembers);
	}

    /**
     * Block room
     */
    public function block() {
        $uid = $this->user->id;
        $room = $this->input('id');
        $this->model->blockRoom($room, $uid);
        $this->okReply();
    }

    /**
     * Unblock room
     */
    public function unblock() {
        $uid = $this->user->id;
        $room = $this->input('id');
        $this->model->unblockRoom($room, $uid);
        $this->okReply();
    }

    /**
     * Notifications
     */
	public function notifications() {
        $uid = $this->user->id;
		$notifications = $this->plugin->notifications($uid);
		$this->jsonReply($notifications);
	}

    /**
     * Setting
     */
    public function setting() {
        $uid = $this->user->id;
        $data = $this->input('data');
		$this->model->setting($uid, $data);
		$this->okReply();
    }

	private function input($name, $default = null) {
		if( isset( $_POST[$name] ) ) return $_POST[$name];
		if( isset( $_GET[$name] ) ) return $_GET[$name]; 
		return $default;
	}

    private function findRoom($obj, $id) {
        $rooms = $obj->roomsByIds($this->user->id, array($id));
        if($rooms && isset($rooms[0])) return $rooms[0];
        return null;
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

    private function isvid($id) {
        return strpos($id, 'vid:') === 0;
    }

    private function roomId($room) {
        return $room->id;
    }

    private function buddyId($buddy) {
        return $buddy->id;
    }

}

?>
