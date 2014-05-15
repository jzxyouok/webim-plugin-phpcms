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
 * WebIM Data Model
 *
 * @package WebIM
 * @autho Ery Lee
 * @since 5.4.1
 */
class Model {

    /**
     * Configure ORM
     */
    public function __construct() {
        global $IMC;
        \ORM::configure('mysql:host=' . $IMC['dbhost']. ';dbname=' . $IMC['dbname']);
        \ORM::configure('username', $IMC['dbuser']);
        \ORM::configure('password', $IMC['dbpassword']);
        \ORM::configure('logging', true);
        \ORM::configure('return_result_sets', true);
        \ORM::configure('driver_options', array(\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'));
    }
    
    /**
     * Get histories 
     *
     * @params string $uid current uid
     * @params string $with the uid that talk with
     * @params 'chat'|'grpchat' $type history type
     * @params integer $limit result limit
     */
    public function histories($uid, $with, $type = 'chat',  $limit = 30) {
        if( $type === 'chat') {
            $query = $this->T('histories')->where('type', 'chat')
                ->whereRaw("(`to`= ? AND `from`= ? AND `fromdel` != 1) OR (`send` = 1 AND `from`= ? AND `to`= ? AND `todel` != 1)", array($with, $uid, $with, $uid))
                ->orderByDesc('timestamp')->limit($limit);
        } else {
            $query = $this->T('histories')->where('type', 'grpchat')
                ->where('to', $with)
                ->where('send', 1)
                ->orderByDesc('timestamp')->limit($limit);
        }
        return array_reverse( array_map( array($this, '_toObj'), $query->findArray() ) );
    }

    /**
     * Get offline histories
     *
     * @params string $uid current uid
     * @params integer $limit result limit
     */
	public function offlineHistories($uid, $limit = 50) {
        $query = $this->T('histories')->where('to', $uid)->whereNotEqual('send', 1)
            ->orderByDesc('timestamp')->limit($limit);
        return array_reverse( array_map( array($this, '_toObj'), $query->findArray() ) );
	}

    /**
     * Save history
     *
     * @params array $message message object
     */
    public function insertHistory($message) {
        $history = $this->T('histories')->create(); 
        $history->set($message)->setExpr('created', 'NOW()');
        $history->save();
    }

    /**
     * Clear histories
     *
     * @params string $uid current uid
     * @params string $with user that talked with
     */
    public function clearHistories($uid, $with) {
        $this->T('histories')->where('from', $uid)->where('to', $with)
            ->findResultSet()
            ->set(array( "fromdel" => 1, "type" => "chat" ))
            ->save();
        $this->T('histories')->where('to', $uid)->where('from', $with)
            ->findResultSet()
            ->set(array( "todel" => 1, "type" => "chat" ))
            ->save();
        $this->T('histories')->where('todel', 1)->where('fromdel', 1)
            ->deleteMany();
    }

    /**
     * Offline histories readed
     *
     * @param string $uid user id
     */
	public function offlineReaded($uid) {
        $this->T('histories')->where('to', $uid)->where('send', 0)->findResultSet()->set('send', 1)->save();
	}

    /**
     * User setting
     *
     * @param string @uid userid
     * @param string @data json 
     *
     * @return object|null
     */
    public function setting($uid, $data = null) {
        $setting = $this->T('settings')->where('uid', $uid)->findOne();
        if (func_num_args() === 1) { //get setting
           if($setting) return json_decode($setting->data); 
            return new \stdClass();
        } 
        //save setting
        if($setting) {
            if(is_string( $data )) {
                $data = stripcslashes( $data );
            } else {
                $data = json_encode( $data );
            }
            $setting->data = $data;
            $setting->save();
        } else {
            $setting = $this->T('settings')->create();
            $setting->set(array(
                'uid' => $uid,
                'data' => $data
            ))->set_expr('created', 'NOW()');
            $setting->save();
        }
    }

    /**
     * All rooms of the user
     *
     * @param string $uid user id
     * @return array rooms array
     */
    public function rooms($uid) {
        $rooms = $this->T('members')
            ->tableAlias('t1')
            ->select('t1.room', 'name')
            ->select('t2.nick', 'nick')
            ->select('t2.url', 'url')
            ->join($this->prefix('rooms'), array('t1.room', '=', 't2.name'), 't2')
            ->where('t1.uid', $uid)->findArray();
        return array_map( array($this, '_roomObj'), $rooms );
    }

    /**
     * Get rooms by ids
     *
     * @param array $ids id list
     * @return array rooms
     */
    public function roomsByIds($uid, $ids) {
        if(empty($ids)) return array();
        $rooms = $this->T('rooms')->whereIn('name', $ids)->findArray();
        return array_map( array($this, '_roomObj'), $rooms );
    }

    /**
     * room object
     */
    private function _roomObj($room) {
        return (object)array(
            'id' => $room['name'],
            'name' => $room['name'],
            'nick' => $room['nick'],
            "url" => $room['url'],
            "pic_url" => WEBIM_IMAGE("room.png"),
            "status" => "",
            "temporary" => true,
            "blocked" => false);
    }

    /**
     * Members of room
     *
     * @param string $room room id
     * @return array members array
     */
    public function members($room) {
        $members = $this->T('members')
            ->select('uid', 'id')
            ->select('nick')
            ->where('room', $room)->findArray();
        return array_map( array($this, '_toObj'), $members );
    }

    /**
     * Create room
     *
     * @param array $data room data
     * @return Room as array
     */
    public function createRoom($data) {
        $name = $data['name'];
        $room = $this->T('rooms')->where('name', $name)->findOne();
        if($room) return $room;
        $room = $this->T('rooms')->create();
        $room->set($data)->set_expr('created', 'NOW()')->set_expr('updated', 'NOW()');
        $room->save();
        return $room;
    }

    /**
     * Invite members to join room
     *
     * $param string $room room id
     * $param array $members member array
     */
    public function inviteRoom($room, $members) {
        foreach($members as $member) {
            $this->joinRoom($room, $member->id, $member->nick);
        }
    }

    /**
     * Join room
     *
     * $param string $room room id
     * $param string $uid user id
     * $param string $nick user nick
     */
    public function joinRoom($room, $uid, $nick) {
        $member = $this->T('members')
            ->where('room', $room)
            ->where('uid', $uid)
            ->findOne();
        if($member == null) {
            $member = $this->T('members')->create();
            $member->set(array(
                'uid' => $uid,
                'nick' => $nick,
                'room' => $room
            ))->set_expr('joined', 'NOW()');
            $member->save();
        }
    }

    /**
     * Leave room
     *
     * $param string $room room id
     * $param string $uid user id
     */
    public function leaveRoom($room, $uid) {
        $this->T('members')->where('room', $room)->where('uid', $uid)->deleteMany();
        //if no members, room deleted...
        $data = $this->T("members")->selectExpr('count(id)', 'total')->where('room', $room)->findOne();
        if($data && $data->total === 0) {
            $this->T('rooms')->where('name', $room)->deleteMany();
        }
    }

    /**
     * Block room
     *
     * $param string $room room id
     * $param string $uid user id
     */
    public function blockRoom($room, $uid) {
        $block = $this->T('blocked')->select('id')
            ->where('room', $room)
            ->where('uid', $uid)->findOne();
        if($block == null) {
            $this->T('blocked')->create()
                ->set('room', $room)
                ->set('uid', $uid)
                ->setExpr('blocked', 'NOW()')
                ->save();
        }
    }

    /**
     * Is room blocked
     *
     * $param string $room room id
     * $param string $uid user id
     *
     * @return true|false
     */
    public function isRoomBlocked($room, $uid) {
        $block = $this->T('blocked')->select('id')->where('uid', $uid)->where('room', $room)->findOne();
        return !(null == $block);
    }

    /**
     * Unblock room
     *
     * @param string $room room id
     * @param string $uid user id
     */
    public function unblockRoom($room, $uid) {
        $this->T('blocked')->where('uid', $uid)->where('room', $room)->deleteMany();
    }

    /**
     * Get visitor
     */
    function visitor() {
        global $_COOKIE, $_SERVER;
        if (isset($_COOKIE['_webim_visitor_id'])) {
            $id = $_COOKIE['_webim_visitor_id'];
        } else {
            $id = substr(uniqid(), 6);
            setcookie('_webim_visitor_id', $id, time() + 3600 * 24 * 30, "/", "");
        }
        $vid = 'vid:'. $id;
        $visitor = $this->T('visitors')->where('name', $vid)->findOne();
        if( !$visitor ) {
            $ipaddr = isset($_SERVER['X-Forwarded-For']) ? $_SERVER['X-Forwarded-For'] : $_SERVER["REMOTE_ADDR"];
            require_once dirname(__FILE__) . '/../vendor/webim/geoip-php/IP.class.php';
            $loc = \IP::find($ipaddr);
            if(is_array($loc)) $loc = implode('',  $loc);
            $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
            $visitor = $this->T('visitors')->create();
            $visitor->set(array(
                "name" => $vid,
                "ipaddr" => $ipaddr,
                "url" => $_SERVER['REQUEST_URI'],
                "referer" => $referer,
                "location" => $loc,
            ))->setExpr('created', 'NOW()');
            $visitor->save();
        }
        return (object) array(
            'id' => $vid,
            'nick' => "v".$id,
            'group' => "visitor",
            'presence' => 'online',
            'show' => "available",
            'pic_url' => WEBIM_IMAGE('male.png'),
            'role' => 'visitor',
            'url' => "#",
            'status' => "",
        );
    }

    /**
     * visitors by vids
     */
    function visitors($vids) {
        if( count($vids)  == 0 ) return array();
        $vids = implode("','", $vids);
        $rows = $this->T('visitors')
            ->select('name')
            ->select('ipaddr')
            ->select('location')
            ->whereIn('name', $vids)
            ->findMany();
        $visitors = array();
        foreach($rows as $v) {
            $status = $v->location;
            if( $v->ipaddr ) $status = $status . '(' . $v->ipaddr .')';
            $visitors[] = (object)array(
                "id" => $v->name,
                "nick" => "v".substr($v->name, 4), //remove vid:
                "group" => "visitor",
                "url" => "#",
                "pic_url" => WEBIM_IMAGE('male.png'),
                "status" => $status, 
            );
        }
        return $visitors;
    }

    /**
     * Table query
     *
     * @param string $table table name
     * @return Query 
     */
    private function T($table) {
        return \ORM::forTable($this->prefix($table)); 
    }

    /**
     * Table name with prefix
     *
     * @param string $table table name
     * @return string table name with prefix
     */
    private function prefix($table) { 
        global $IMC; return $IMC['dbprefix'] . $table;
    }

    private function _toObj($v) {
        return (object)$v;
    }

}

?>
