<?php

namespace WebIM;

class PHPCMS_Plugin extends Plugin {

    private $member_db; 

    public function __construct() {
        \pc_base::load_sys_class('param');
        $this->member_db = \pc_base::load_model('member_model');
        parent::__construct();
    }

	protected function uid() {
        return \param::get_cookie('_userid');
    }

    protected function user($uid) {
        $memberinfo = $this->member_db->get_one(array('userid'=>intval($uid)), 'nickname');
		return array(
            'uid' => $uid,
            'id' => $uid,
            'nick' => $memberinfo['nickname'],
            'presence' => 'online',
            'show' => "available",
            'pic_url' => WEBIM_IMAGE('male.png'),
            'url' => "#",
            'role' => 'user',
            'status' => "",
        );
    }

    /**
     * TOOD: self as buddy
     */
    public function buddies($uid) {
        if($this->isvid($uid)) {
            $buddy = array(
                'id' => $uid,
                'uid' => $uid,
                'nick' => 'v'. preg_replace('/vid:/', '', $uid),
                'pic_url' => WEBIM_IMAGE('male.png'),
                'group' => 'visitor',
                'url' => "#",
                'status' => "",
            );
        } else {
            $member = $this->member_db->get_one(array('userid'=>intval($uid)), 'nickname');
            $buddy = array(
                'uid' => $uid,
                'id' => $uid,
                'nick' => $member['nickname'],
                'pic_url' => WEBIM_IMAGE('male.png'),
                'url' => "#",
                'status' => "",
            );
        }
        return array($buddy);
    }

    /**
     * TODO:
     */
    public function buddiesByIds($ids) {
        return parent::buddiesByIds($ids);
    }

    /**
     * TODO:
     */
    public function room($id) {
        return parent::room($id);
    }

    /**
     * TODO:
     */
    public function rooms($uid) {
        return parent::rooms($uid);
    }

    /**
     * TODO:
     */
    public function roomsByIds($ids) {
        return parent::roomsByIds($ids);
    }

    /**
     * TODO:
     */
    public function members($room) {
        return parent::members($room);
    }

    /**
     * TODO:
     */
    public function notifications($uid) {
        return parent::notifications($uid);
    }

}

