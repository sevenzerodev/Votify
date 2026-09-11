<?php

class SecondEconomyProvider extends EconomyProvider {

	const DEFAULT_PLUGIN_NAME = "EcoSys";

	public function isAvailable() {
		$eco = $this->getEconomyPlugin();
		return $eco !== null && $eco->isEnabled();
	}

	public function addMoney($p, $amount) {
		$eco = $this->getEconomyPlugin();
		if ($eco === null || !$eco->isEnabled()) {
			return false;
		}
		$eco->addBal($p->getName(), $amount);
		return true;
	}

	public function getName() {
		$name = $this->plugin->getConfigValue("economy.second-plugin-name", self::DEFAULT_PLUGIN_NAME);
		return $name === "" ? self::DEFAULT_PLUGIN_NAME : $name;
	}

	private function getEconomyPlugin() {
		$name = $this->plugin->getConfigValue("economy.second-plugin-name", self::DEFAULT_PLUGIN_NAME);
		if ($name === "") {
			$name = self::DEFAULT_PLUGIN_NAME;
		}
		return $this->plugin->getServer()->getPluginManager()->getPlugin($name);
	}
}
