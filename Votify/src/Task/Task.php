<?php

use pocketmine\scheduler\PluginTask;

class AutoCheckTask extends PluginTask {

	public function onRun($currentTick) {
		$this->getOwner()->runAutoCheck();
	}
}
