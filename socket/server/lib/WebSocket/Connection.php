<?php
namespace WebSocket;

/**
 * WebSocket connection class
 */
class Connection
{
	private $server;
	private $socket;
	private $handshaked = false;
	private $debug = false;
	private $application = null;	
	
	private $ip;
	private $port;
	private $connectionId = null;
	public $infos = array();
	
	public function __construct($server, $socket) {
		$this->server = $server;
		$this->socket = $socket;

		// set some client-information:
		socket_getpeername($this->socket, $ip, $port);
		$this->ip = $ip;
		$this->port = $port;
		$this->connectionId = md5($ip . $port . spl_object_hash($this));

		$this->infos['ip'] = $this->ip;
		$this->infos['port'] = $this->port;
		$this->infos['id'] = $this->connectionId;
		
		$this->log('Connected');
	}
	
	/**
	 * Make handshaking
	 */
	private function handshake($data) {
		$this->log('Performing handshake');		
		$lines = preg_split("/\r\n/", $data);
		
		// check for valid http-header:
		if(!preg_match('/\AGET (\S+) HTTP\/1.1\z/', $lines[0], $matches)) {
			$this->log('Invalid request: ' . $lines[0]);
			socket_close($this->socket);
			return false;
		}				
		$applicationName = substr($matches[1], 1);
		
		// generate headers array:
		$headers = array();
		foreach($lines as $line) {
			$line = chop($line);
			if(preg_match('/\A(\S+): (.*)\z/', $line, $matches)) {
				$headers[$matches[1]] = $matches[2];
			}
		}

		if(isset($headers['Sec-WebSocket-Protocol'])) {
			$applicationName = $headers['Sec-WebSocket-Protocol'];
		}
		
		// check for valid application:
		$this->application = $this->server->getApplication($applicationName);
		if(!$this->application) {
			$this->log('Invalid application: ' . $applicationName);
			socket_close($this->socket);
			return false;
		}

		
		
		
		// check for supported websocket version:
		if(!isset($headers['Sec-WebSocket-Version']) || $headers['Sec-WebSocket-Version'] < 6) {
			$this->log('Unsupported websocket version.');
			socket_close($this->socket);
			return false;
		}			
		
		// do handyshake: (hybi-10)
		$secKey = $headers['Sec-WebSocket-Key'];
		$secAccept = base64_encode(pack('H*', sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
		$response = "HTTP/1.1 101 Switching Protocols\r\n";
		$response.= "Upgrade: websocket\r\n";
		$response.= "Connection: Upgrade\r\n";
		$response.= "Sec-WebSocket-Accept: " . $secAccept . "\r\n";
		if(isset($headers['Sec-WebSocket-Protocol'])) {
			$response.= "Sec-WebSocket-Protocol: " . $applicationName . "\r\n";
		}
		$response.= "\r\n";
		
		//echo "\n\n" .$response."\n\n";
		
		socket_write($this->socket, $response, strlen($response));		
		$this->handshaked = true;
		$this->log('Handshake sent');
		$this->application->connect($this);
		
		return true;			
	}
	
	/**
	 * Fired when data are received
	 */
	public function data($data) {		
		if ($this->handshaked) {			
			$this->handle($data);
		} else {
			$this->handshake($data);
		}
	}
	
	
	/**
	 * Fired when data are received and handshaking is done
	 */
	private function handle($data) {		
		$decodedData = $this->hybi10Decode($data);		
		
		switch($decodedData['type'])
		{
			case 'text':
				if($this->debug)
					$this->log('> '.$decodedData['payload']);
				$this->application->data($decodedData['payload'], $this);
			break;			
		
			case 'ping':
				$this->send($decodedData['payload'], 'pong', false);
				$this->log('Ping? Pong!');
			break;
		
			case 'pong':
				// server currently not sending pings, so no pong should be received.
			break;
		
			case 'close':			
				$this->close();
			break;
		}
		
		return true;
	}   
	
	/**
	 * Send message
	 */
	public function send($payload, $type = 'text', $masked = false) {	
		if($this->debug)
			$this->log('< '.$payload);
		
		$encodedData = $this->hybi10Encode($payload, $type, $masked);
		if(!socket_write($this->socket, $encodedData, strlen($encodedData)))
		{
			socket_close($this->socket);
			$this->socket = false;
		}
	}
	
	/**
	 * Close connection
	 */
	public function close($statusCode = 1000) {
		$payload = str_split(sprintf('%016b', $statusCode), 8);
		$payload[0] = chr(bindec($payload[0]));
		$payload[1] = chr(bindec($payload[1]));
		$payload = implode('', $payload);

		switch($statusCode)
		{
			case 1000:
				$payload .= 'normal closure';
			break;
		
			case 1001:
				$payload .= 'going away';
			break;
		
			case 1002:
				$payload .= 'protocol error';
			break;
		
			case 1003:
				$payload .= 'unknown data (opcode)';
			break;
		
			case 1004:
				$payload .= 'frame too large';
			break;		
		
			case 1007:
				$payload .= 'utf8 expected';
			break;
		}
		$this->send($payload, 'close', false);
		
		if($this->application)
		{
			$this->application->disconnect($this);
		}
		socket_close($this->socket);
		$this->log('Disconnected');
		$this->server->removeClient($this->socket);
	}


	/**
	 * Disconnect user
	 */
	public function disconnect() {
		$this->log('Disconnected', 'info');
		
		if($this->application)
		{
			$this->application->disconnect($this);
		}		
		socket_close($this->socket);
	}	 

	/**
	 * Log message
	 */
	public function log($message, $type = 'info') {		
		$this->server->log('[client ' . $this->ip . ':' . $this->port . '] ' . $message, $type);
	}
	
	
	/**
	 * Hanshake made with protocol hybi10
	 */
	private function hybi10Encode($payload, $type = 'text', $masked = false) 	{
		$frameHead = array();
		$frame = '';
		$payloadLength = strlen($payload);
		
		switch($type)
		{		
			case 'text':
				// first byte indicates FIN, Text-Frame (10000001):
				$frameHead[0] = 129;				
			break;			
		
			case 'close':
				// first byte indicates FIN, Close Frame(10001000):
				$frameHead[0] = 136;
			break;
		
			case 'ping':
				// first byte indicates FIN, Ping frame (10001001):
				$frameHead[0] = 137;
			break;
		
			case 'pong':
				// first byte indicates FIN, Pong frame (10001010):
				$frameHead[0] = 138;
			break;
		}
		
		// set mask and payload length (using 1, 3 or 9 bytes) 
		if($payloadLength > 65535)
		{
			$payloadLengthBin = str_split(sprintf('%064b', $payloadLength), 8);
			$frameHead[1] = ($masked === true) ? 255 : 127;
			for($i = 0; $i < 8; $i++)
			{
				$frameHead[$i+2] = bindec($payloadLengthBin[$i]);
			}
			// most significant bit MUST be 0 (close connection if frame too big)
			if($frameHead[2] > 127)
			{
				$this->close(1004);
				return false;
			}
		}
		elseif($payloadLength > 125)
		{
			$payloadLengthBin = str_split(sprintf('%016b', $payloadLength), 8);
			$frameHead[1] = ($masked === true) ? 254 : 126;
			$frameHead[2] = bindec($payloadLengthBin[0]);
			$frameHead[3] = bindec($payloadLengthBin[1]);
		}
		else
		{
			$frameHead[1] = ($masked === true) ? $payloadLength + 128 : $payloadLength;
		}

		// convert frame-head to string:
		foreach(array_keys($frameHead) as $i)
		{
			$frameHead[$i] = chr($frameHead[$i]);
		}
		if($masked === true)
		{
			// generate a random mask:
			$mask = array();
			for($i = 0; $i < 4; $i++)
			{
				$mask[$i] = chr(rand(0, 255));
			}
			
			$frameHead = array_merge($frameHead, $mask);			
		}						
		$frame = implode('', $frameHead);

		// append payload to frame:
		$framePayload = array();	
		for($i = 0; $i < $payloadLength; $i++)
		{		
			$frame .= ($masked === true) ? $payload[$i] ^ $mask[$i % 4] : $payload[$i];
		}

		return $frame;
	}
	
	/**
	 * Decode received message with protocol hybi10
	 */
	function hybi10Decode($data) {		
		$payloadLength = '';
		$mask = '';
		$unmaskedPayload = '';
		$decodedData = array();
		
		// estimate frame type:
		$firstByteBinary = sprintf('%08b', ord($data[0]));		
		$secondByteBinary = sprintf('%08b', ord($data[1]));
		$opcode = bindec(substr($firstByteBinary, 4, 4));
		$isMasked = ($secondByteBinary[0] == '1') ? true : false;
		$payloadLength = ord($data[1]) & 127;
		
		// close connection if unmasked frame is received:
		if($isMasked === false)
		{
			$this->close(1002);
		}
		
		switch($opcode)
		{
			// text frame:
			case 1:
				$decodedData['type'] = 'text';				
			break;
			
			// connection close frame:
			case 8:
				$decodedData['type'] = 'close';
			break;
			
			// ping frame:
			case 9:
				$decodedData['type'] = 'ping';				
			break;
			
			// pong frame:
			case 10:
				$decodedData['type'] = 'pong';
			break;
			
			default:
				// Close connection on unknown opcode:
				$this->close(1003);
			break;
		}
		
		if($payloadLength === 126)
		{
		   $mask = substr($data, 4, 4);
		   $payloadOffset = 8;
		}
		elseif($payloadLength === 127)
		{
			$mask = substr($data, 10, 4);
			$payloadOffset = 14;
		}
		else
		{
			$mask = substr($data, 2, 4);	
			$payloadOffset = 6;
		}
		
		$dataLength = strlen($data);
		
		if($isMasked === true)
		{
			for($i = $payloadOffset; $i < $dataLength; $i++)
			{
				$j = $i - $payloadOffset;
				$unmaskedPayload .= $data[$i] ^ $mask[$j % 4];
			}
			$decodedData['payload'] = $unmaskedPayload;
		}
		else
		{
			$payloadOffset = $payloadOffset - 4;
			$decodedData['payload'] = substr($data, $payloadOffset);
		}
		
		return $decodedData;
	}
	
	public function getClientIp() {
		return $this->ip;
	}
	
	public function getClientPort() {
		return $this->port;
	}
	
	public function getClientId() {
		return $this->connectionId;
	}
}