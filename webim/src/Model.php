<?php

namespace WebIM;

function TName($table) { return 'webim_' . $table; }

function T($table) { return \ORM::forTable(TName($table)); }

/**
 * 数据模型类
 */
class Model {

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
     * 读取历史消息
     */
    public function histories($uid, $with, $type = 'chat',  $limit = 30) {
        if( $type === 'chat') {
            $query = T('histories')->where('type', 'chat')
                ->whereRaw("(`to`= ? AND `from`= ? AND `fromdel` != 1) OR (`send` = 1 AND `from`= ? AND `to`= ? AND `todel` != 1)", array($with, $uid, $with, $uid))
                ->orderByDesc('timestamp')->limit($limit);
        } else {
            $query = T('histories')->where('type', 'grpchat')
                ->where('to', $with)
                ->where('send', 1)
                ->orderByDesc('timestamp')->limit($limit);
        }
        return array_reverse($query->findArray());
    }

    /**
     * 读取用户的离线消息
     */
	public function offlineHistories($uid, $limit = 50) {
        $query = T('histories')->where('to', $uid)->whereNotEqual('send', 1)
            ->orderByDesc('timestamp')->limit($limit);
        return array_reverse( $query->findArray() );
	}

    /**
     * 保存历史消息
     */
    public function insertHistory($message) {
        $history = T('histories')->create(); 
        $history->set($message)->setExpr('created', 'NOW()');
        $history->save();
    }

    /**
     * 清除历史消息
     */
    public function clearHistories($uid, $with) {
        T('histories')->where('from', $uid)->where('to', $with)
            ->findResultSet()
            ->set(array( "fromdel" => 1, "type" => "chat" ))
            ->save();
        T('histories')->where('to', $uid)->where('from', $with)
            ->findResultSet()
            ->set(array( "todel" => 1, "type" => "chat" ))
            ->save();
        T('histories')->where('todel', 1)->where('fromdel', 1)
            ->deleteMany();
    }

    /**
     * 离线消息已被读取 
     */
	public function offlineReaded($uid) {
        T('histories')->where('to', $uid)->where('send', 0)->findResultSet()->set('send', 1)->save();
	}

    /**
     * 用户设置
     */
    public function setting($uid, $data = null) {
        $setting = T('settings')->where('uid', $uid)->findOne();
        if (func_num_args() === 1) { //get setting
           if($setting) return json_decode($setting->data); 
           return null;
        } 
        //save setting
        if($setting) {
            if(!is_string($data)) { $data = json_decode($data); }
            $setting->data = $data;
            $setting->save();
        } else {
            $setting = T('settings')->create();
            $setting->set(array(
                'uid' => $uid,
                'data' => $data
            ))->set_expr('created', 'NOW()');
            $setting->save();
        }
    }

    /**
     * 根据ID读取讨论组
     */
    public function room($id) {
        $room = T('rooms')->where('name', $id)->findOne();
        if($room) {
            return array(
                'id' => $room->name,
                'name' => $room->name,
                'nick' => $room->nick,
                "url" => "#",
                "pic_url" => WEBIM_IMAGE("room.png"),
                "status" => "",
                "temporary" => true,
                "blocked" => false
            );
        }
        return null;
    }

    /**
     * 读取用户的全部讨论组
     */
    public function rooms($uid) {
        $rooms = T('members')
            ->tableAlias('t1')
            ->select('t1.room', 'name')
            ->select('t2.nick', 'nick')
            ->join(TName('rooms'), array('t1.room', '=', 't2.name'), 't2')
            ->where('t1.uid', $uid)->findMany();
        $rtRooms = array();
        foreach($rooms as $room) {
            $rtRooms[] = array(
                'id' => $room->name,
                'nick' => $room->nick,
                "url" => "#",
                "pic_url" => WEBIM_IMAGE("room.png"),
                "status" => "",
                "temporary" => true,
                "blocked" => $this->isRoomBlocked($room->name, $uid)
            );
        }
        return $rtRooms;
    }

    /**
     * 读取讨论组成员
     */
    public function members($room) {
        return T('members')
            ->select('uid', 'id')
            ->select('nick')
            ->where('room', $room)->findArray();
    }

    /**
     * 创建讨论组
     */
    public function createRoom($data) {
        $name = $data['name'];
        $room = T('rooms')->where('name', $name)->findOne();
        if($room) return $room;
        $room = T('rooms')->create();
        $room->set($data)->set_expr('created', 'NOW()')->set_expr('updated', 'NOW()');
        $room->save();
        return $room->asArray();
    }

    /**
     * 邀请成员加入讨论组
     */
    public function inviteRoom($room, $members) {
        foreach($members as $member) {
            $this->joinRoom($room, $member['uid'], $member['nick']);
        }
    }

    /**
     * 用户加入讨论组
     */
    public function joinRoom($room, $uid, $nick) {
        $member = T('members')
            ->where('room', $room)
            ->where('uid', $uid)
            ->findOne();
        if($member == null) {
            $member = T('members')->create();
            $member->set(array(
                'uid' => $uid,
                'nick' => $nick,
                'room' => $room
            ))->set_expr('joined', 'NOW()');
            $member->save();
        }
    }

    /**
     * 用户离开讨论组
     */
    public function leaveRoom($room, $uid) {
        T('members')->where('room', $room)->where('uid', $uid)->deleteMany();
        //if no members, room deleted...
        $data = T("members")->selectExpr('count(id)', 'total')->where('room', $room)->findOne();
        if($data && $data->total === 0) {
            T('rooms')->where('name', $room)->deleteMany();
        }
    }

    /**
     * 屏蔽讨论组
     */
    public function blockRoom($room, $uid) {
        $block = T('blocked')->select('id')
            ->where('room', $room)
            ->where('uid', $uid)->findOne();
        if($block == null) {
            T('blocked')->create()
                ->set('room', $room)
                ->set('uid', $uid)
                ->setExpr('blocked', 'NOW()')
                ->save();
        }
    
    }

    /**
     * 讨论组是否屏蔽
     */
    public function isRoomBlocked($room, $uid) {
        $block = T('blocked')->select('id')->where('uid', $uid)->where('room', $room)->findOne();
        return !(null == $block);
    }

    /**
     * 解除讨论组屏蔽
     */
    public function unblockRoom($room, $uid) {
        T('blocked')->where('uid', $uid)->where('room', $room)->deleteMany();
    }

}


