<?php
namespace WebSocket;

/**
 * Server class
 */
class Server extends Socket
{   
	private $clients = array();

	private $applications = array();
	
	private $events = array();
	
	private $time = 1000;
	
	private $continue = true;

	public function __construct($host = 'localhost', $port = 8000, $max = 100) {
		parent::__construct($host, $port, $max);

		$this->log('Server created');
		$this->log('Host : '.$host.':'.$port);
	}

	/**
	 *	Start the server
	 */
	public function run() {
		while ($this->continue) {
			$changed_sockets = $this->allsockets;
			@socket_select($changed_sockets, $write = NULL, $except = NULL, 0, $this->time * 1000);
			foreach ($this->applications as $application) {
				$application->onTick();
			}
			foreach ($changed_sockets as $socket) {
				if ($socket == $this->master) {
					if (($ressource = socket_accept($this->master)) < 0) {
						$this->log('Socket error: ' . socket_strerror(socket_last_error($ressource)));
						continue;
					} else {
						$client = new Connection($this, $ressource);						
						$this->clients[$ressource] = $client;
						$this->allsockets[] = $ressource;
					}
				} else {
					$client = $this->clients[$socket];
					$bytes = @socket_recv($socket, $data, 4096, 0);
					if ($bytes === 0) {
						$client->disconnect();
						unset($this->clients[$socket]);
						$index = array_search($socket, $this->allsockets);
						unset($this->allsockets[$index]);
						unset($client);
					} else {
						$client->data($data);
					}
				}
			}
		}
	}

	public function getApplication($key) {
		if (array_key_exists($key, $this->applications)) {
			return $this->applications[$key];
		} else {
			return false;
		}
	}
	
	public function registerApplication($key, $application) {
		$application->server = $this;
		$this->applications[$key] = $application;
	}
	
	/**
	 *	Write a log information
	 */
	public function log($message, $type = 'info') {
		echo date('Y-m-d H:i:s') . ' [' . ($type ? $type : 'error') . '] ' . $message . PHP_EOL;
	}
	
	/**
	 *	Remove a connected client
	 */
	public function removeClient($resource) {
		$client = $this->clients[$resource];
		unset($this->clients[$resource]);
		$index = array_search($resource, $this->allsockets);
		unset($this->allsockets[$index]);
		unset($client);
	}
	
	/**
	 *	Stop the server
	 */
	public function stopServer() {
	   $this->continue = false;
	}
	
	public function setRefreshTime($time) {
	   $this->time = $time;
	}
}