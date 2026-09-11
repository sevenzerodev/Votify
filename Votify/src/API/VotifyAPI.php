<?php

class VotifyAPI {

	const BASE_URL = "https://minecraftpocket-servers.com/api/";

	public static function getCheckUrl($key, $username) {
		return self::BASE_URL . "?object=votes&element=claim&key=" . urlencode($key) . "&username=" . urlencode($username);
	}

	public static function getClaimUrl($key, $username) {
		return self::BASE_URL . "?action=post&object=votes&element=claim&key=" . urlencode($key) . "&username=" . urlencode($username);
	}

	public static function getVotersUrl($key, $period) {
		return self::BASE_URL . "?object=servers&element=voters&key=" . urlencode($key) . "&month=" . urlencode($period) . "&format=json";
	}

	public static function getVotesUrl($key, $limit = null, $nickname = null) {
		$url = self::BASE_URL . "?object=servers&element=votes&key=" . urlencode($key) . "&format=json";
		if ($limit !== null) {
			$url .= "&limit=" . urlencode($limit);
		}
		if ($nickname !== null) {
			$url .= "&nickname=" . urlencode($nickname);
		}
		return $url;
	}

	public static function getServerInfoUrl($key) {
		return self::BASE_URL . "?object=servers&element=detail&key=" . urlencode($key);
	}
}
