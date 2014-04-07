<?php

/**
 * Some simple unit tests to help test this library.
 */

require_once dirname(__FILE__).'/../src/WebIM/WebIM.php';
//require_once 'PHPUnit/Framework.php';

class WebIMTest extends PHPUnit_Framework_TestCase {

    private $webim;

    protected function setUp() {
        $domain   = 'localhost';
        $apikey   = 'public';
        $server   = 'http://localhost:8000';
        $buddies  = ['uid2', 'uid3'];
        $rooms    = ['room1', 'room2'];
        $endpoint = array(
            'id' => 'uid1',
            'nick' => 'User1',
            'show' => 'available',
            'status' => 'online',
        );
        $this->webim = new \WebIM\WebIM($endpoint, $domain, $apikey, $server);
        $this->webim->online($buddies, $rooms);
    }

    public function testOnline() {
        $this->dump('Online', $this->webim->online(['uid4', 'uid5'], ['room6', 'room7']));
    }


    public function testOffline() {
        $this->dump('Offline', $this->webim->offline()); 
    }

    public function testPresences() {
        $this->dump('Presences', $this->webim->presences(['uid1', 'uid2', 'uid3']));
    }

    public function testPresence() {
        $this->dump('Presence', $this->webim->presence('away', 'Away'));
    }

    public function testStatus() {
        $this->dump('Send Status', $this->webim->status('uid2', 'typing'));
    }

    public function testMessage() {
        $this->dump('Send Message', $this->webim->message(null, 'uid2', 'blabla'));
    }

    public function testPush() {
        $this->dump('Push Message', $this->webim->message('uid1', 'uid2', 'blabla'));
    }

    public function testMembers() {
        $this->dump('Members', $this->webim->members('room1'));
    }

    public function testJoin() {
        $this->dump('Join', $this->webim->join('room3'));
    }

    public function testLeave() {
        $this->webim->join('room3');
        $this->dump('Leave', $this->webim->leave('room3'));
    }

    private function dump($title, $data) {
        echo $title . ': ' . json_encode($data) . PHP_EOL;
    }

}

