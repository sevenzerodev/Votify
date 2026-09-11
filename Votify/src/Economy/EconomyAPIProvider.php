<?php

class EconomyAPIProvider extends EconomyProvider {

	public function isAvailable() {
		$eco = $this->plugin->getServer()->getPluginManager()->getPlugin("EconomyAPI");
		return $eco !== null && $eco->isEnabled();
	}

	public function addMoney($p, $amount) {
		$eco = $this->plugin->getServer()->getPluginManager()->getPlugin("EconomyAPI");
		if ($eco === null || !$eco->isEnabled()) {
			return false;
		}
		$eco->addMoney($p, $amount);
		return true;
	}

	public function getName() {
		return "EconomyAPI";
	}
}
