<?php
error_reporting(E_ALL);

//use if you're loading the php page from browser
/*ob_implicit_flush(true);
set_time_limit(0);
ignore_user_abort(1);*/


// load SplClassLoader
require(__DIR__ . '/lib/SplClassLoader.php');

// intitialize SplClassLoader
$classLoader = new SplClassLoader('WebSocket', __DIR__ . '/lib');
$classLoader->register();

// define host and port
$host = 'localhost';
$port = 8000;

// launch server
$server = new \WebSocket\Server($host, $port);
$server->registerApplication('chat', \WebSocket\Application\ChatApplication::getInstance());
$server->run();
?>
