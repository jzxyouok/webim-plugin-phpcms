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
        return array_reverse($query->findArray());
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
        return array_reverse( $query->findArray() );
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
            if(!is_string($data)) { $data = json_decode($data); }
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
        return array_map(function($room) {
            return array(
                'id' => $room['name'],
                'nick' => $room['nick'],
                "url" => $room['url'],
                "pic_url" => WEBIM_IMAGE("room.png"),
                "status" => "",
                "temporary" => true,
                "blocked" => false);
        }, $rooms);
    }

    /**
     * Get rooms by ids
     *
     * @param array $ids id list
     * @return array rooms
     */
    public function roomsByIds($ids) {
        if(empty($ids)) return array();
        $rooms = $this->T('rooms')->whereIn('name', $ids)->findArray();
        return array_map(function($room) {
            return array(
                'id' => $room['name'],
                'name' => $room['name'],
                'nick' => $room['nick'],
                "url" => $room['url'],
                "pic_url" => WEBIM_IMAGE("room.png"),
                "status" => "",
                "temporary" => true,
                "blocked" => false);
        }, $rooms);
    }

    /**
     * Members of room
     *
     * @param string $room room id
     * @return array members array
     */
    public function members($room) {
        return $this->T('members')
            ->select('uid', 'id')
            ->select('nick')
            ->where('room', $room)->findArray();
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
        return $room->asArray();
    }

    /**
     * Invite members to join room
     *
     * $param string $room room id
     * $param array $members member array
     */
    public function inviteRoom($room, $members) {
        foreach($members as $member) {
            $this->joinRoom($room, $member['uid'], $member['nick']);
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
        global $IMC;
        return $IMC['dbprefix'] . $table;
    }


}


