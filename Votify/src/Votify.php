<?php

use pocketmine\plugin\PluginBase;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use pocketmine\item\Item;

require_once(__DIR__ . "/API/VotifyAPI.php");
require_once(__DIR__ . "/API/VotifyHttpTask.php");
require_once(__DIR__ . "/API/VoteClaimTask.php");
require_once(__DIR__ . "/Economy/EconomyProvider.php");
require_once(__DIR__ . "/Economy/EconomyAPIProvider.php");
require_once(__DIR__ . "/Economy/SecondEconomyProvider.php");
require_once(__DIR__ . "/Economy/EconomyManager.php");
require_once(__DIR__ . "/Command/VoteCommand.php");
require_once(__DIR__ . "/Task/AutoCheckTask.php");
require_once(__DIR__ . "/Listener/PlayerListener.php");

class Votify extends PluginBase {

	private $economyManager;
	private $votersCache = array();
	private $votesCache = array();
	private $serverInfoCache = null;
	private $cooldowns = array();

	public function onEnable() {
		@mkdir($this->getDataFolder());
		$this->saveDefaultConfig();
		$this->reloadConfig();

		$this->votersCache = array();
		$this->votesCache = array();
		$this->cooldowns = array();

		$this->economyManager = new EconomyManager($this);
		$this->economyManager->setup();

		$this->getServer()->getPluginManager()->registerEvents(new PlayerListener($this), $this);
		$this->getCommand("vote")->setExecutor(new VoteCommand($this));

		if ($this->getConfigValue("auto-check", true)) {
			$interval = (int) $this->getConfigValue("check-interval", 300);
			if ($interval < 20) {
				$interval = 20;
			}
			$ticks = $interval * 20;
			$this->getServer()->getScheduler()->scheduleDelayedRepeatingTask(new AutoCheckTask($this), $ticks, $ticks);
		}

		$this->getLogger()->info("Votify has been enabled. Economy provider: " . $this->economyManager->getProviderName());
	}

	public function reloadVotifyConfig() {
		$this->reloadConfig();
		$this->votersCache = array();
		$this->votesCache = array();
		$this->economyManager->setup();
	}

	public function getConfigValue($path, $default = null) {
		$data = $this->getConfig()->getAll();
		$parts = explode(".", $path);
		$cur = $data;
		foreach ($parts as $part) {
			if (is_array($cur) && array_key_exists($part, $cur)) {
				$cur = $cur[$part];
			} else {
				return $default;
			}
		}
		return $cur;
	}

	public function msg($key, $default, $replacements = array()) {
		$prefix = $this->getConfigValue("messages.prefix", "");
		$val = $this->getConfigValue("messages." . $key, $default);
		foreach ($replacements as $find => $replace) {
			$val = str_replace($find, $replace, $val);
		}
		return $prefix . $val;
	}

	public function line($path, $default, $replacements = array()) {
		$val = $this->getConfigValue($path, $default);
		foreach ($replacements as $find => $replace) {
			$val = str_replace($find, $replace, $val);
		}
		return $val;
	}

	public function sendVoteInfo($p) {
		$url = $this->getConfigValue("vote-url", "");
		$p->sendMessage($this->msg("vote-info", "§6Vote for the server at: §e{URL}", array("{URL}" => $url)));
	}

	public function requestCheck($p) {
		if (!($p instanceof Player)) {
			$p->sendMessage("This command can only be used in-game.");
			return;
		}

		$key = $this->getConfigValue("api-key", "");
		if ($key === "") {
			$p->sendMessage($this->msg("invalid-api-key", "§cThe server's voting API key is not configured correctly. Contact an administrator."));
			return;
		}

		if ($this->isOnCooldown($p->getName())) {
			$p->sendMessage($this->msg("cooldown", "§cPlease wait before checking again."));
			return;
		}
		$this->setCooldown($p->getName());

		$url = VotifyAPI::getCheckUrl($key, $p->getName());
		$task = new VotifyHttpTask($url, "check", $p->getName(), null);
		$this->getServer()->getScheduler()->scheduleAsyncTask($task);
	}

	public function requestClaim($p) {
		if (!($p instanceof Player)) {
			$p->sendMessage("This command can only be used in-game.");
			return;
		}

		$key = $this->getConfigValue("api-key", "");
		if ($key === "") {
			$p->sendMessage($this->msg("invalid-api-key", "§cThe server's voting API key is not configured correctly. Contact an administrator."));
			return;
		}

		if ($this->isOnCooldown($p->getName())) {
			$p->sendMessage($this->msg("cooldown", "§cPlease wait before checking again."));
			return;
		}
		$this->setCooldown($p->getName());

		$checkUrl = VotifyAPI::getCheckUrl($key, $p->getName());
		$claimUrl = VotifyAPI::getClaimUrl($key, $p->getName());
		$task = new VoteClaimTask($checkUrl, $claimUrl, $p->getName());
		$this->getServer()->getScheduler()->scheduleAsyncTask($task);
	}

	public function requestTop($p) {
		$senderName = ($p instanceof Player) ? $p->getName() : "CONSOLE";

		$key = $this->getConfigValue("api-key", "");
		if ($key === "") {
			$this->notifySender($senderName, $this->msg("invalid-api-key", "§cThe server's voting API key is not configured correctly. Contact an administrator."));
			return;
		}

		$period = $this->getConfigValue("top.period", "current");
		$this->fetchVoters($period, $senderName, "top");
	}

	public function requestStats($p) {
		$senderName = ($p instanceof Player) ? $p->getName() : "CONSOLE";

		$key = $this->getConfigValue("api-key", "");
		if ($key === "") {
			$this->notifySender($senderName, $this->msg("invalid-api-key", "§cThe server's voting API key is not configured correctly. Contact an administrator."));
			return;
		}

		$this->notifySender($senderName, $this->line("stats.header", "§6===== §eVoting Statistics §6====="));
		$this->fetchVoters("current", $senderName, "stats_current");
		$this->fetchVoters("previous", $senderName, "stats_previous");
		$this->fetchVotes($senderName, "stats_total");
		$this->fetchServerInfo($senderName);
	}

	public function runAutoCheck() {
		$key = $this->getConfigValue("api-key", "");
		if ($key === "") {
			return;
		}

		$autoClaim = $this->getConfigValue("auto-claim", true);

		foreach ($this->getServer()->getOnlinePlayers() as $p) {
			$name = $p->getName();
			if ($this->isOnCooldown($name)) {
				continue;
			}
			$this->setCooldown($name);

			if ($autoClaim) {
				$checkUrl = VotifyAPI::getCheckUrl($key, $name);
				$claimUrl = VotifyAPI::getClaimUrl($key, $name);
				$task = new VoteClaimTask($checkUrl, $claimUrl, $name);
			} else {
				$url = VotifyAPI::getCheckUrl($key, $name);
				$task = new VotifyHttpTask($url, "check", $name, null);
			}

			$this->getServer()->getScheduler()->scheduleAsyncTask($task);
		}
	}

	public function onPlayerJoin($p) {
		if (!$this->getConfigValue("reward-on-join", true)) {
			return;
		}

		$key = $this->getConfigValue("api-key", "");
		if ($key === "") {
			return;
		}

		$name = $p->getName();
		if ($this->isOnCooldown($name)) {
			return;
		}
		$this->setCooldown($name);

		$checkUrl = VotifyAPI::getCheckUrl($key, $name);
		$claimUrl = VotifyAPI::getClaimUrl($key, $name);
		$task = new VoteClaimTask($checkUrl, $claimUrl, $name);
		$this->getServer()->getScheduler()->scheduleAsyncTask($task);
	}

	public function handleApiResponse($type, $username, $result, $extra) {
		if ($type === "check") {
			$this->processCheckResult($username, $result);
		} elseif ($type === "voters") {
			$this->processVotersResult($username, $result, $extra);
		} elseif ($type === "votes") {
			$this->processVotesResult($username, $result, $extra);
		} elseif ($type === "serverinfo") {
			$this->processServerInfoResult($username, $result);
		}
	}

	private function processCheckResult($username, $result) {
		$p = $this->getServer()->getPlayer($username);
		if ($p === null) {
			return;
		}

		if ($result === false) {
			$p->sendMessage($this->msg("api-error", "§cCould not reach the voting API. Please try again later."));
			return;
		}

		$status = trim($result);
		if ($status === "0") {
			$p->sendMessage($this->msg("not-voted", "§cYou haven't voted yet! Use §e/vote §cto vote."));
		} elseif ($status === "1") {
			$p->sendMessage($this->msg("unclaimed", "§aYou have an unclaimed vote! Use §e/vote claim §ato receive your reward."));
		} elseif ($status === "2") {
			$p->sendMessage($this->msg("already-claimed", "§cYou have already claimed your vote."));
		} else {
			$p->sendMessage($this->msg("api-error", "§cCould not reach the voting API. Please try again later."));
		}
	}

	public function handleClaimResult($username, $result) {
		$p = $this->getServer()->getPlayer($username);
		if ($p === null) {
			return;
		}

		if ($result === "0") {
			$p->sendMessage($this->msg("not-voted", "§cYou haven't voted yet! Use §e/vote §cto vote."));
		} elseif ($result === "2") {
			$p->sendMessage($this->msg("already-claimed", "§cYou have already claimed your vote."));
		} elseif ($result === "claimed") {
			$this->giveVoteReward($p);
		} elseif ($result === "failed") {
			$p->sendMessage($this->msg("claim-failed", "§cFailed to claim your vote. Please try again later."));
		} else {
			$p->sendMessage($this->msg("api-error", "§cCould not reach the voting API. Please try again later."));
		}
	}

	private function giveVoteReward($p) {
		$amount = (float) $this->getConfigValue("economy.reward", 0);

		if ($amount > 0) {
			if ($this->economyManager->isEnabled()) {
				$this->economyManager->giveReward($p, $amount);
			} else {
				$p->sendMessage($this->msg("economy-unavailable", "§cVoting rewards are currently unavailable."));
			}
		}

		if ($this->getConfigValue("rewards.items.enabled", false)) {
			$items = $this->getConfigValue("rewards.items.list", array());
			if (is_array($items)) {
				foreach ($items as $entry) {
					$parts = explode(":", $entry);
					$id = isset($parts[0]) ? (int) $parts[0] : 0;
					$meta = isset($parts[1]) ? (int) $parts[1] : 0;
					$count = isset($parts[2]) ? (int) $parts[2] : 1;
					if ($id > 0) {
						$item = Item::get($id, $meta, $count);
						$p->getInventory()->addItem($item);
					}
				}
			}
		}

		if ($this->getConfigValue("rewards.commands.enabled", false)) {
			$commands = $this->getConfigValue("rewards.commands.list", array());
			if (is_array($commands)) {
				foreach ($commands as $command) {
					$command = str_replace("{PLAYER}", $p->getName(), $command);
					$this->getServer()->dispatchCommand($this->getServer()->getConsoleSender(), $command);
				}
			}
		}

		$p->sendMessage($this->msg("reward-received", "§aThanks for voting! You received §e{REWARD} coins§a!", array("{REWARD}" => $amount)));

		if ($this->getConfigValue("broadcast.enabled", true)) {
			$broadcastMsg = str_replace("{PLAYER}", $p->getName(), $this->getConfigValue("broadcast.message", ""));
			$this->getServer()->broadcastMessage($broadcastMsg);
		}
	}

	private function fetchVoters($period, $senderName, $purpose) {
		$cacheMinutes = (int) $this->getConfigValue("cache.voters-minutes", 5);
		if (isset($this->votersCache[$period]) && (time() - $this->votersCache[$period]["time"]) < ($cacheMinutes * 60)) {
			$this->deliverVoters($this->votersCache[$period]["data"], $period, $senderName, $purpose);
			return;
		}

		$key = $this->getConfigValue("api-key", "");
		if ($key === "") {
			return;
		}

		$url = VotifyAPI::getVotersUrl($key, $period);
		$task = new VotifyHttpTask($url, "voters", $senderName, array("period" => $period, "purpose" => $purpose));
		$this->getServer()->getScheduler()->scheduleAsyncTask($task);
	}

	private function fetchVotes($senderName, $purpose) {
		$cacheMinutes = (int) $this->getConfigValue("cache.votes-minutes", 5);
		if (isset($this->votesCache["all"]) && (time() - $this->votesCache["all"]["time"]) < ($cacheMinutes * 60)) {
			$this->deliverVotes($this->votesCache["all"]["data"], $senderName, $purpose);
			return;
		}

		$key = $this->getConfigValue("api-key", "");
		if ($key === "") {
			return;
		}

		$url = VotifyAPI::getVotesUrl($key);
		$task = new VotifyHttpTask($url, "votes", $senderName, array("purpose" => $purpose));
		$this->getServer()->getScheduler()->scheduleAsyncTask($task);
	}

	private function fetchServerInfo($senderName) {
		$cacheMinutes = (int) $this->getConfigValue("cache.server-info-minutes", 10);
		if (isset($this->serverInfoCache) && (time() - $this->serverInfoCache["time"]) < ($cacheMinutes * 60)) {
			$this->deliverServerInfo($this->serverInfoCache["data"], $senderName);
			return;
		}

		$key = $this->getConfigValue("api-key", "");
		if ($key === "") {
			return;
		}

		$url = VotifyAPI::getServerInfoUrl($key);
		$task = new VotifyHttpTask($url, "serverinfo", $senderName, null);
		$this->getServer()->getScheduler()->scheduleAsyncTask($task);
	}

	private function processServerInfoResult($senderName, $result) {
		if ($result === false) {
			return;
		}

		$data = json_decode($result, true);
		if (!is_array($data)) {
			return;
		}

		$this->serverInfoCache = array("time" => time(), "data" => $data);
		$this->deliverServerInfo($data, $senderName);
	}

	private function deliverServerInfo($data, $senderName) {
		$rank = "N/A";
		foreach (array("rank", "position", "ranking") as $key) {
			if (isset($data[$key])) {
				$rank = $data[$key];
				break;
			}
		}
		$this->notifySender($senderName, $this->line("stats.server-rank", "§7Server rank: §a{RANK}", array("{RANK}" => $rank)));
	}

	private function processVotersResult($senderName, $result, $extra) {
		if ($result === false) {
			$this->notifySender($senderName, $this->msg("api-error", "§cCould not reach the voting API. Please try again later."));
			return;
		}

		$data = json_decode($result, true);
		if (!is_array($data)) {
			$this->notifySender($senderName, $this->msg("api-error", "§cCould not reach the voting API. Please try again later."));
			return;
		}

		$period = $extra["period"];
		$purpose = $extra["purpose"];
		$this->votersCache[$period] = array("time" => time(), "data" => $data);
		$this->deliverVoters($data, $period, $senderName, $purpose);
	}

	private function processVotesResult($senderName, $result, $extra) {
		if ($result === false) {
			$this->notifySender($senderName, $this->msg("api-error", "§cCould not reach the voting API. Please try again later."));
			return;
		}

		$data = json_decode($result, true);
		if (!is_array($data)) {
			$this->notifySender($senderName, $this->msg("api-error", "§cCould not reach the voting API. Please try again later."));
			return;
		}

		$this->votesCache["all"] = array("time" => time(), "data" => $data);
		$this->deliverVotes($data, $senderName, $extra["purpose"]);
	}

	private function deliverVoters($data, $period, $senderName, $purpose) {
		$list = $this->extractVoterList($data);

		if ($purpose === "top") {
			usort($list, array($this, "compareVotes"));

			$amount = (int) $this->getConfigValue("top.amount", 10);
			$lines = array();
			$lines[] = $this->getConfigValue("top.header", "§6===== §eTop Voters §6=====");

			$format = $this->getConfigValue("top.format", "§e{POSITION}. §f{PLAYER} §7- §a{VOTES} votes");
			$i = 1;
			foreach ($list as $entry) {
				if ($i > $amount) {
					break;
				}
				$lines[] = str_replace(array("{POSITION}", "{PLAYER}", "{VOTES}"), array($i, $entry["name"], $entry["votes"]), $format);
				$i++;
			}

			$lines[] = $this->getConfigValue("top.footer", "§6=========================");
			$this->notifyLines($senderName, $lines);
		} elseif ($purpose === "stats_current" || $purpose === "stats_previous") {
			$total = 0;
			foreach ($list as $entry) {
				$total += $entry["votes"];
			}
			$key = $purpose === "stats_current" ? "current-month" : "previous-month";
			$this->notifySender($senderName, $this->line("stats." . $key, "§7Votes: §a{VOTES}", array("{VOTES}" => $total)));
		}
	}

	private function deliverVotes($data, $senderName, $purpose) {
		$list = isset($data["votes"]) ? $data["votes"] : $data;
		$total = is_array($list) ? count($list) : 0;

		if ($purpose === "stats_total") {
			$this->notifySender($senderName, $this->line("stats.total-votes", "§7Total votes: §a{VOTES}", array("{VOTES}" => $total)));
		}
	}

	private function extractVoterList($data) {
		$list = array();
		$raw = isset($data["voters"]) ? $data["voters"] : $data;

		if (!is_array($raw)) {
			return $list;
		}

		foreach ($raw as $entry) {
			if (!is_array($entry)) {
				continue;
			}

			$name = "Unknown";
			foreach (array("username", "nickname", "name") as $key) {
				if (isset($entry[$key])) {
					$name = $entry[$key];
					break;
				}
			}

			$votes = 0;
			foreach (array("votes", "count", "total") as $key) {
				if (isset($entry[$key])) {
					$votes = (int) $entry[$key];
					break;
				}
			}

			$list[] = array("name" => $name, "votes" => $votes);
		}

		return $list;
	}

	private function compareVotes($a, $b) {
		return $b["votes"] - $a["votes"];
	}

	private function notifySender($senderName, $message) {
		if ($senderName === "CONSOLE") {
			$this->getLogger()->info(TextFormat::clean($message));
		} else {
			$p = $this->getServer()->getPlayer($senderName);
			if ($p !== null) {
				$p->sendMessage($message);
			}
		}
	}

	private function notifyLines($senderName, $lines) {
		foreach ($lines as $line) {
			$this->notifySender($senderName, $line);
		}
	}

	private function isOnCooldown($name) {
		$name = strtolower($name);
		if (!isset($this->cooldowns[$name])) {
			return false;
		}
		$cooldown = (int) $this->getConfigValue("cooldown", 60);
		return (time() - $this->cooldowns[$name]) < $cooldown;
	}

	private function setCooldown($name) {
		$this->cooldowns[strtolower($name)] = time();
	}
}
