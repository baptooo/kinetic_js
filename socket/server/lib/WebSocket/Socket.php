<?php

namespace WebSocket;

/**
 *	Main socket class
 */
class Socket
{
	// Master socket
	protected $master;

	// All connected sockets
	protected $allsockets = array();

	public function __construct($host = 'localhost', $port = 8000, $max = 100) {
		ob_implicit_flush(true);
		$this->createSocket($host, $port);
	}

	/**
	 * Create a socket on given host/port
	 */
	private function createSocket($host, $port) {
		if(!($this->master = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)))
		{
			die("socket_create() failed, reason: " . socket_strerror(socket_last_error()));
		}		
		
		socket_set_option($this->master, SOL_SOCKET, SO_REUSEADDR, 1);		
		
		if(!socket_bind($this->master, $host, $port))
		{
			die("socket_bind() failed, reason: " . socket_strerror(socket_last_error($this->master)));
		}		
		
		if(!socket_listen($this->master, 5))
		{
			die("socket_listen() failed, reason: " . socket_strerror(socket_last_error($this->master)));
		}		
		
		$this->allsockets[] = $this->master;
	}	

	/**
	 * Sends a message
	 * $client : the socket
	 * $msg  : the message to send
	 */
	protected function send($client, $msg) {
		socket_write($client, $msg, strlen($msg));
	}
}