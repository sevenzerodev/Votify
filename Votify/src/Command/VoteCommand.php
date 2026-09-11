<?php

use pocketmine\command\Command;
use pocketmine\command\CommandExecutor;
use pocketmine\command\CommandSender;

class VoteCommand implements CommandExecutor {

	private $plugin;

	public function __construct($plugin) {
		$this->plugin = $plugin;
	}

	public function onCommand(CommandSender $p, Command $cmd, $label, array $args) {
		if (!$p->hasPermission("votify.command.vote")) {
			$p->sendMessage($this->plugin->msg("no-permission", "§cYou do not have permission to use this command."));
			return true;
		}

		if (count($args) === 0) {
			$this->plugin->sendVoteInfo($p);
			return true;
		}

		$sub = strtolower($args[0]);

		if ($sub === "check") {
			if (!$p->hasPermission("votify.command.vote.check")) {
				$p->sendMessage($this->plugin->msg("no-permission", "§cYou do not have permission to use this command."));
				return true;
			}
			$this->plugin->requestCheck($p);
		} elseif ($sub === "claim") {
			if (!$p->hasPermission("votify.command.vote.claim")) {
				$p->sendMessage($this->plugin->msg("no-permission", "§cYou do not have permission to use this command."));
				return true;
			}
			$this->plugin->requestClaim($p);
		} elseif ($sub === "top") {
			if (!$p->hasPermission("votify.command.vote.top")) {
				$p->sendMessage($this->plugin->msg("no-permission", "§cYou do not have permission to use this command."));
				return true;
			}
			$this->plugin->requestTop($p);
		} elseif ($sub === "stats") {
			if (!$p->hasPermission("votify.command.vote.stats")) {
				$p->sendMessage($this->plugin->msg("no-permission", "§cYou do not have permission to use this command."));
				return true;
			}
			$this->plugin->requestStats($p);
		} elseif ($sub === "reload") {
			if (!$p->hasPermission("votify.command.reload")) {
				$p->sendMessage($this->plugin->msg("no-permission", "§cYou do not have permission to use this command."));
				return true;
			}
			$this->plugin->reloadVotifyConfig();
			$p->sendMessage($this->plugin->msg("reload-success", "§aVotify configuration reloaded."));
		} else {
			$this->plugin->sendVoteInfo($p);
		}

		return true;
	}
}
