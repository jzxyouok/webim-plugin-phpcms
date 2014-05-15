<?php

namespace WebIM;

class PHPCMS_Plugin extends Plugin {

    private $member_db; 

    public function __construct() {
        \pc_base::load_sys_class('param');
        $this->member_db = \pc_base::load_model('member_model');
        parent::__construct();
    }

    /**
     * API: current user
     *
     * @return object current user
     */
    public function user() {
        $uid = \param::get_cookie('_userid');
        if( !$uid ) return null;
        $memberinfo = $this->member_db->get_one(array('userid'=>intval($uid)), 'nickname');
		return (object)array(
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
        //visitor, return empty buddies
        if($this->isvid($uid))  return array();
        $member = $this->member_db->get_one(array('userid'=>intval($uid)), 'nickname');
        $buddy = (object) array(
            'id' => $uid,
            'nick' => $member['nickname'],
            'group' => 'friend',
            'presence' => 'offline',
            'show' => 'unavailable',
            'pic_url' => WEBIM_IMAGE('male.png'),
            'url' => "#",
            'status' => "",
        );
        return array($buddy);
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
    public function buddiesByIds($uid, $ids) {
        if( empty($ids) ) return array();
        $ids = implode("','", $ids);
        $members = $this->member_db->select("userid in ('{$uids}')");
        $buddies = array();
        foreach($members as $m) {
            $buddies[] = (object)array(
                'id' => $member['userid'],
                'nick' => $member['nickname'],
                'group' => 'friend',
                'presence' => 'offline',
                'show' => 'unavailable',
                'pic_url' => WEBIM_IMAGE('male.png'),
                'url' => "#",
                'status' => ""
            );
        }
        return $buddies;
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
        return parent::rooms($uid);
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
	public function roomsByIds($uid, $ids) {
        return parent::roomsByIds($uid, $ids);
    }

    /**
     * API: members of room
     *
     * $param $room string roomid
     * 
     */
    public function members($room) {
        return parent::members($room);
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
        return parent::notifications($uid);
    }

    private function isvid($id) {
        return strpos($id, 'vid:') === 0;
    }

}

?>
