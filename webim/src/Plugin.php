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
 * WebIM Plugin
 *
 * @package WebIM
 * @autho Ery Lee
 * @since 5.4.1
 */
class Plugin {

	/*
	 * Current user or visitor 
	 */
	protected $user = null;

	/*
	 * Init User
	 */
    public function __construct() {
        global $IMC;
        $uid = $this->uid();
		if($uid) {
            $this->user = $this->user($uid);
		} else if($IMC['visitor']) {//visitor 
			$this->user = $this->visitor();
        } else {//no user or visitor

        }
	}

	/*
	 * API: uid of logined user
     *
     * @return string uid of logined user
	 */
	protected function uid() {
        global $_SESSION;
		return isset($_SESSION['uid']) ? $_SESSION['uid'] : null;
	}

	/*
	 * API: load user
     *
     * @return array user
	 */
	protected function user($uid) {
		//NOTICE: demo user
		return array(
            'uid' => $uid,
            'id' => $uid,
            'nick' => preg_replace('/uid/', 'user', $uid),
            'presence' => 'online',
            'show' => "available",
            'pic_url' => WEBIM_IMAGE('male.png'),
            'url' => "#",
            'role' => 'user',
            'status' => "",
        );
	}

	/*
	 * API: load visitor
     *
     * @return array visitor
	 */
	protected function visitor() {
		if ( isset($_COOKIE['_webim_visitor_id']) ) {
			$id = $_COOKIE['_webim_visitor_id'];
		} else {
			$id = substr(uniqid(), 6);
			setcookie('_webim_visitor_id', $id, time() + 3600 * 24 * 30, "/", "");
		}
        $vid = $this->_vid($id);
        return array(
            'uid' => $vid,
            'id' => $vid, 
            'nick' => "v".$id,
            'presence' => 'online',
            'show' => "available",
            'pic_url' => WEBIM_IMAGE('male.png'),
            'role' => 'visitor',
            'url' => "#",
            'status' => "",
        );
	}

    /**
     * Is visitor id?
     *
     * @return true|false
     */
    protected function isvid($uid) {
        return strpos($uid, 'vid:') === 0;
    }

    /**
     * Current user 
     *
     * @return array current user 
     */
    public function currentUser() {
        return $this->user;
    }

    /**
     * Is logined?
     *
     * @return true|false
     */
    public function logined() {
        return ($this->user != null);
    }

	/*
	 * API: Buddies of current user.
     *
     * @param string $uid current uid
	 *
     * @return array Buddy list
     *
	 * Buddy:
	 *
	 * 	id:         uid
	 * 	uid:        uid
	 *	nick:       nick
	 *	pic_url:    url of photo
     *	presence:   online | offline
	 *	show:       available | unavailable | away | busy | hidden
	 *  url:        url of home page of buddy 
	 *  status:     buddy status information
	 *  group:      group of buddy
	 *
	 */
	public function buddies($uid) {
        //TODO: DEMO Code
        return array_map(function($id){
            return array(
                'id' => 'uid' . $id,
                'uid' => 'uid' . $id,
                'group' => 'friend',
                'nick' => 'user'.$id,
                'presence' => 'offline',
                'show' => 'unavailable',
                'status' => '#',
                'pic_url' => WEBIM_IMAGE('male.png')
            );
        
        }, range(1, 10));
	}

	/*
	 * API: buddies by ids
	 *
     * @param array $ids buddy id array
     *
     * @return array Buddy list
     *
	 * Buddy
	 */
	public function buddiesByIds($ids) {
        return array_map(function($id) {
            return array(
                'id' => $id,
                'uid' => $id,
                'group' => 'friend',
                'nick' => preg_replace('/uid/', 'user', $id),
                'presence' => 'offline',
                'show' => 'unavailable',
                'status' => '#',
                'pic_url' => WEBIM_IMAGE('male.png')
            );
        }, $ids);
	}

	/*
	 * API：rooms of current user
     * 
     * @param string $uid 
     *
     * @return array rooms
     *
	 * Room:
	 *
	 *	id:		    Room ID,
	 *	nick:	    Room Nick
	 *	url:	    Home page of room
	 *	pic_url:    Pic of Room
	 *	status:     Room status 
	 *	count:      count of online members
	 *	all_count:  count of all members
	 *	blocked:    true | false
	 */
	public function rooms($uid) {
        //TODO: DEMO CODE
		$room = array(
			'id' => 'room1',
            'name' => 'room1',
			'nick' => 'Room',
			'url' => "#",
			'pic_url' => WEBIM_IMAGE('room.png'),
			'status' => "Room",
			'blocked' => false,
            'temporary' => false
		);
		return array( $room );	
	}

	/*
	 * API: rooms by ids
     *
     * @param array id array
     *
     * @return array rooms
	 *
	 * Room
     *
	 */
	function roomsByIds($ids) {
        $rooms = array();
        foreach($ids as $id) {
            if($id === 'room1') { 
                $rooms[] = array(
                    'id' => $id,
                    'name' => $id,
                    'nick' => 'room'.$id,
                    'url' => "#",
                    'pic_url' => WEBIM_IMAGE('room.png')
                );
            }
        }
		return $rooms;
	}

    /**
     * API: members of room
     *
     * $param $room string roomid
     * 
     */
    function members($room) {
        //TODO: DEMO CODE
        return array_map(function($id) {
            return array(
                'id' => 'uid' . $id,
                'uid' => 'uid' . $id,
                'nick' => 'user'.$id
            ); 
        }, range(1, 10));
    }

	/*
	 * API: notifications of current user
	 *
     * @return array  notification list
     *
	 * Notification:
	 *
	 * 	text: text
	 * 	link: link
	 */	
	public function notifications($uid) {
        $noti = array('text' => 'Notification', 'link' => '#');
		return array($noti);
	}

    /**
     * Visitor id
     *
     * @param string $id raw id
     * @return string visitor id
     */
    protected function _vid($id) { 
        return 'vid:'.$id; 
    }

}

