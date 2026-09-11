<?php

use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;

class VoteClaimTask extends AsyncTask {

	private $checkUrl;
	private $claimUrl;
	private $username;

	public function __construct($checkUrl, $claimUrl, $username) {
		$this->checkUrl = $checkUrl;
		$this->claimUrl = $claimUrl;
		$this->username = $username;
	}

	public function onRun() {
		$checkResponse = $this->httpGet($this->checkUrl);
		if ($checkResponse === false) {
			$this->setResult("error");
			return;
		}

		$status = trim($checkResponse);
		if ($status !== "1") {
			$this->setResult($status);
			return;
		}

		$claimResponse = $this->httpGet($this->claimUrl);
		if ($claimResponse === false) {
			$this->setResult("error");
			return;
		}

		$claimStatus = trim($claimResponse);
		$this->setResult($claimStatus === "1" ? "claimed" : "failed");
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
		$plugin->handleClaimResult($this->username, $this->getResult());
	}
}
