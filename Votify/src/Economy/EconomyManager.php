<?php

class EconomyManager {

	private $plugin;
	private $provider;
	private $enabled = false;

	public function __construct($plugin) {
		$this->plugin = $plugin;
	}

	public function setup() {
		$this->provider = null;
		$this->enabled = false;

		$choice = strtolower($this->plugin->getConfigValue("economy.provider", "auto"));

		$economyApi = new EconomyAPIProvider($this->plugin);
		$second = new SecondEconomyProvider($this->plugin);

		if ($choice === "economyapi") {
			if ($economyApi->isAvailable()) {
				$this->provider = $economyApi;
			} else {
				$this->plugin->getLogger()->warning("EconomyAPI was selected as the economy provider but is not available. Vote rewards are disabled.");
			}
		} elseif ($choice === "second") {
			if ($second->isAvailable()) {
				$this->provider = $second;
			} else {
				$this->plugin->getLogger()->warning("The second economy provider was selected but is not available. Vote rewards are disabled.");
			}
		} else {
			if ($economyApi->isAvailable()) {
				$this->provider = $economyApi;
			} elseif ($second->isAvailable()) {
				$this->provider = $second;
			} else {
				$this->plugin->getLogger()->warning("No supported economy plugin was found. Vote rewards are disabled.");
			}
		}

		$this->enabled = $this->provider !== null;
	}

	public function isEnabled() {
		return $this->enabled;
	}

	public function getProviderName() {
		return $this->provider === null ? "none" : $this->provider->getName();
	}

	public function giveReward($p, $amount) {
		if (!$this->enabled || $this->provider === null || $amount <= 0) {
			return false;
		}
		return $this->provider->addMoney($p, $amount);
	}
}
