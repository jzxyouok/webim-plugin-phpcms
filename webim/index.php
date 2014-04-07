<?php

/**
 * WebIM 入口
 *
 * @copyright   (C) 2014 NexTalk.IM
 * @license     http://nextalk.im/license
 * @lastmodify  2014-04-06
 */ 

if(phpversion() < '5.3.10') {
    exit('PHP version should be > 5.3.10');
}

include dirname(__FILE__) . '/../phpcms/base.php';

define('WEBIM_DEBUG', true);

if(WEBIM_DEBUG) {
    session_start();
	error_reporting( E_ALL );
} else {
	error_reporting( E_ALL & ~E_NOTICE & ~E_STRICT );
}

define('WEBIM_VERSION', '5.4');

define('WEBIM_PRODUCT', 'phpcms');

function WEBIM_PATH() {
	global $_SERVER;
    $name = htmlspecialchars($_SERVER['SCRIPT_NAME'] ? $_SERVER['SCRIPT_NAME'] : $_SERVER['PHP_SELF']); 
    return substr( $name, 0, strrpos( $name, '/' ) ) . "/";
}

function WEBIM_IMAGE($img) {
    return WEBIM_PATH() . "static/images/{$img}";
}

/**
 * Configuration
 */
$IMC = require('config.php');

if( !$IMC['isopen'] ) exit();

define('WEBIM_ROOT', dirname(__FILE__));

define('WEBIM_SRC', WEBIM_ROOT . '/src');

/**
 *
 * WebIM Libraries
 *
 * https://github.com/webim/webim-php
 *
 */
require WEBIM_ROOT.'/vendor/autoload.php';

/**
 * Model
 */
require WEBIM_SRC . '/Model.php';

/**
 * Base Plugin
 */
require WEBIM_SRC . '/Plugin.php';

/**
 * Router
 */
require WEBIM_SRC . '/Router.php';

/**
 * WebIM APP
 */
require WEBIM_SRC . '/App.php';

/**
 * PHPCMS Plugin
 */
require WEBIM_ROOT . '/PHPCMS_Plugin.php';

\WebIM\App::run(new \WebIM\PHPCMS_Plugin());

