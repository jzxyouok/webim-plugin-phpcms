# WebIM-PHP

A PHP WebIM library for interacting with the [NexTalk](http://nextalk.im) Server.

NexTalk is a web-based instant messaging server.

## Composer Installation

Installed with Composer (http://getcomposer.org/).  Add the following to your
composer.json file.  Composer will handle the autoloading.

```json
{
    "require": {
        "webim/webim-php": "*"
    }
}
```

## Usage

```php
$endpoint = array(
    'id' => 'uid1',
    'nick' => 'User1',
    'status' => 'Online',
    'show' => 'available',
);
$domain = 'www.example.com';
$apikey = 'akakakakakdka';
$server = 'http://nextalk.im:8000';
$client = new WebIM\Client($endpoint, $domain, $apikey, $server);

$buddy_ids = ['uid2', 'uid3'];
$room_ids = ['room1', 'room2'];
$client.online($buddy_ids, $room_ids);

$client.message(null, 'uid2', 'blabla');

```

## Testing

To test the library itself, run the PHPUnit tests:

    phpunit tests/

## Author

http://nextalk.im

ery.lee at gmail.com
