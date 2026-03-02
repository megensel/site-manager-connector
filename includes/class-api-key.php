<?php
/**
 * API key generation, storage, and validation for Site Manager Connector.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Site_Manager_Connector_Api_Key {

	const OPTION_KEY = 'site_manager_api_key';
	const KEY_LENGTH  = 32; // 32 bytes = 64 hex chars

	/**
	 * Generate a cryptographically secure 64-char hex API key.
	 *
	 * @return string
	 */
	public static function generate() {
		$bytes = random_bytes( self::KEY_LENGTH );
		return bin2hex( $bytes );
	}

	/**
	 * Get the stored API key (or generate and store on first run).
	 *
	 * @return string
	 */
	public static function get() {
		$key = get_option( self::OPTION_KEY, '' );
		if ( empty( $key ) ) {
			$key = self::generate();
			update_option( self::OPTION_KEY, $key );
		}
		return $key;
	}

	/**
	 * Regenerate and save a new API key.
	 *
	 * @return string New key.
	 */
	public static function regenerate() {
		$key = self::generate();
		update_option( self::OPTION_KEY, $key );
		return $key;
	}

	/**
	 * Validate the given key against the stored key.
	 *
	 * @param string $supplied_key Key from request header.
	 * @return bool
	 */
	public static function validate( $supplied_key ) {
		if ( empty( $supplied_key ) || ! is_string( $supplied_key ) ) {
			return false;
		}
		$stored = self::get();
		return hash_equals( $stored, $supplied_key );
	}

	/**
	 * Run on plugin activation: ensure a key exists.
	 */
	public static function activate() {
		self::get();
	}
}
