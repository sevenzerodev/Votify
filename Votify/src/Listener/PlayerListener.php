<?php

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;

class PlayerListener implements Listener {

	private $plugin;

	public function __construct($plugin) {
		$this->plugin = $plugin;
	}

	public function onJoin(PlayerJoinEvent $ev) {
		$this->plugin->onPlayerJoin($ev->getPlayer());
	}
}
