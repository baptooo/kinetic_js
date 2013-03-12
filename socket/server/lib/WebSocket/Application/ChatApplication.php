<?php

namespace WebSocket\Application;

class ChatApplication extends Application
{
	public $a;
	
	/**
	 * Fired every x milliseconds (specidied in server class)
	 */
	public function onTick() {
		$this->a++;
		if($this->a > 300)
			$this->stop();
	}
	
	/**
	 * Fired when user is disconnecting
	 */
	public function onDisconnect($client) {
		$this->sendSystemMessage('*', $client->infos['pseudo'] ." s'est déconnecté.");
	}

	/**
	 * Fired when user send data
	 */
	public function onData($data, $client) {
		
		$action = $data->action;
		$data = htmlspecialchars ($data->data);
		
		switch($action) {
			case 'nickselect':
				$this->checkPseudo($client, $data);
			break;
			case 'msg':
				$this->hasMessage($client, $data);
			break;
		}
	}
	
	/**
	 * Fired when user send a message
	 */
	private function hasMessage($client, $data) {
		@$datas = split(" ", $data);
		
		switch($datas[0]) {
		
			case '/time':
				$this->sendSystemMessage($client, date('d/m/Y H:i:s'));
			break;
			
			case '/quit':
				$this->remove($client);
			break;
			
			case '/system':
				$msg = '';
				for($i=1;$i<count($datas);$i++) {$msg.=$datas[$i].' ';}
				$this->sendSystemMessage('*', $msg);		
			break;
			
			case '/shutdown':
				for($i = 5; $i>0 ; $i--) {
					$this->sendSystemMessage('*', "Le serveur va s'éteindre dans ".$i."s");
					sleep(1);
				}
				$this->stop();
			break;
			default:
				$this->sendMessage('*', $client->infos['pseudo'], $client->infos['color'], $data);
		}
	}
	
	/**
	 * Send message
	 */
	private function sendMessage($client, $from, $color, $msg) {
		$payload = array(
			'action' => 'msg',
			'pseudo' => $from,
			'color' => $color,
			'data' => $msg
		);
		$this->send($client, $payload);
	}
	
	
	/**
	 * Send system message
	 */
	private function sendSystemMessage($client, $msg) {
		$payload = array(
			'action' => 'system',
			'data' => $msg
		);
		$this->send($client, $payload);
	}
	
	/**
	 * Check if pseudo is not already used and choose a color
	 */
	private function checkPseudo($client, $pseudo) {
		if($pseudo != '') {
		
			$available = true;
			foreach($this->clients as $person) {
				if(isset($person->infos['pseudo'])) {
					if($person->infos['pseudo'] == $pseudo) {
						$available = false;
						break;
					}
				}
			}
			
			if($available) {
				$colors = array('FF0000', 'D50195', '9501D5', '0F01D5', '0195D5', '01D5CE', '01D564', '01D508', '9DD501', 'E5D601', 'FEA408', 'B30000', '068906');
				$color = $colors[rand(0, count($colors)-1)];
				$client->infos['pseudo'] = $pseudo;
				$client->infos['color'] = $color;	
				
				$this->sendSystemMessage('*', $pseudo ." s'est connecté.");
			} else {
				$this->sendSystemMessage($client, "Ce pseudo est déjà pris!");
			}
			
		} else {
			$this->sendSystemMessage($client, "Pseudo invalide!");
		}
	}
}