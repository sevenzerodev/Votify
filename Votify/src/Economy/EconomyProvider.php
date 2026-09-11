<?php

abstract class EconomyProvider {

	protected $plugin;

	public function __construct($plugin) {
		$this->plugin = $plugin;
	}

	abstract public function isAvailable();

	abstract public function addMoney($p, $amount);

	abstract public function getName();
}
