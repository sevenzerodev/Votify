<?php

use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;

class VotifyHttpTask extends AsyncTask {

	private $url;
	private $type;
	private $username;
	private $extra;

	public function __construct($url, $type, $username = null, $extra = null) {
		$this->url = $url;
		$this->type = $type;
		$this->username = $username;
		$this->extra = $extra === null ? null : serialize($extra);
	}

	public function onRun() {
		$response = $this->httpGet($this->url);
		$this->setResult($response);
	}

	private function httpGet($url) {
		if (!function_exists("curl_init")) {
			return false;
		}
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_USERAGENT, "Votify/1.0.0");
		$response = curl_exec($ch);
		curl_close($ch);
		return $response;
	}

	public function onCompletion(Server $server) {
		$plugin = $server->getPluginManager()->getPlugin("Votify");
		if ($plugin === null || !$plugin->isEnabled()) {
			return;
		}
		$extra = $this->extra === null ? null : unserialize($this->extra);
		$plugin->handleApiResponse($this->type, $this->username, $this->getResult(), $extra);
	}
}
