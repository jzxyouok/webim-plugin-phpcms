<?php

namespace WebIM;

/**
 * WebIM APP
 */
class App {

    public static function run($plugin = null) {

        if($plugin == null) $plugin = new \WebIM\Plugin();

        $router = new \WebIM\Router();

        $router->model( new \WebIM\Model() );

        $router->plugin( $plugin );

        $router->route();
    
    }

}

