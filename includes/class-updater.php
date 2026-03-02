<?php
/**
 * Self-updater: checks a remote JSON manifest for new versions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Site_Manager_Connector_Updater {

	const OPTION_UPDATE_URL = 'site_manager_update_url';
	const DEFAULT_UPDATE_URL = 'https://raw.githubusercontent.com/megensel/site-manager-connector/main/update-info.json';
	const TRANSIENT_KEY = 'site_manager_connector_update_data';
	const CACHE_TTL = 43200; // 12 hours
	const PLUGIN_FILE = 'site-manager-connector/site-manager-connector.php';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'check_for_update' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_info' ), 20, 3 );
		add_filter( 'upgrader_post_install', array( __CLASS__, 'after_install' ), 10, 3 );
	}

	/**
	 * Get the configured update URL (or default).
	 */
	private static function get_update_url() {
		$url = get_option( self::OPTION_UPDATE_URL, '' );
		return ! empty( $url ) ? $url : self::DEFAULT_UPDATE_URL;
	}

	/**
	 * Fetch remote update info (cached via transient).
	 */
	private static function get_remote_info() {
		$cached = get_transient( self::TRANSIENT_KEY );
		if ( false !== $cached ) {
			return $cached ?: null; // cached empty string means previous fetch failed
		}

		$url = self::get_update_url();
		$response = wp_remote_get( $url, array(
			'timeout' => 10,
			'headers' => array( 'Accept' => 'application/json' ),
		) );

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			// Cache failure for 1 hour to avoid hammering.
			set_transient( self::TRANSIENT_KEY, '', 3600 );
			return null;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body );

		if ( empty( $data ) || empty( $data->version ) ) {
			set_transient( self::TRANSIENT_KEY, '', 3600 );
			return null;
		}

		set_transient( self::TRANSIENT_KEY, $data, self::CACHE_TTL );
		return $data;
	}

	/**
	 * Hook: pre_set_site_transient_update_plugins
	 * Add our plugin to the update transient if a newer version is available.
	 */
	public static function check_for_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$remote = self::get_remote_info();
		if ( ! $remote || empty( $remote->version ) ) {
			return $transient;
		}

		$current_version = defined( 'SITE_MANAGER_CONNECTOR_VERSION' ) ? SITE_MANAGER_CONNECTOR_VERSION : '0.0.0';

		if ( version_compare( $remote->version, $current_version, '>' ) ) {
			$plugin = new stdClass();
			$plugin->slug        = 'site-manager-connector';
			$plugin->plugin      = self::PLUGIN_FILE;
			$plugin->new_version = $remote->version;
			$plugin->url         = 'https://github.com/megensel/site-manager-connector';
			$plugin->package     = isset( $remote->download_url ) ? $remote->download_url : '';
			$plugin->icons       = array();
			$plugin->banners     = array();
			$plugin->tested      = isset( $remote->tested_wp ) ? $remote->tested_wp : '';
			$plugin->requires_php = isset( $remote->requires_php ) ? $remote->requires_php : '';
			$plugin->compatibility = new stdClass();

			$transient->response[ self::PLUGIN_FILE ] = $plugin;
		}

		return $transient;
	}

	/**
	 * Hook: plugins_api
	 * Return plugin details for the "View Details" modal.
	 */
	public static function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( ! isset( $args->slug ) || 'site-manager-connector' !== $args->slug ) {
			return $result;
		}

		$remote = self::get_remote_info();
		if ( ! $remote ) {
			return $result;
		}

		$info = new stdClass();
		$info->name          = 'Site Manager Connector';
		$info->slug          = 'site-manager-connector';
		$info->version       = $remote->version;
		$info->author        = '<a href="https://github.com/megensel">Site Manager</a>';
		$info->homepage      = 'https://github.com/megensel/site-manager-connector';
		$info->download_link = isset( $remote->download_url ) ? $remote->download_url : '';
		$info->requires      = isset( $remote->requires_wp ) ? $remote->requires_wp : '5.9';
		$info->tested        = isset( $remote->tested_wp ) ? $remote->tested_wp : '';
		$info->requires_php  = isset( $remote->requires_php ) ? $remote->requires_php : '7.4';
		$info->sections      = array(
			'changelog' => isset( $remote->changelog ) ? $remote->changelog : '',
		);

		return $info;
	}

	/**
	 * Hook: upgrader_post_install
	 * Ensure correct directory name after install and re-activate if needed.
	 */
	public static function after_install( $response, $hook_extra, $result ) {
		if ( ! isset( $hook_extra['plugin'] ) || self::PLUGIN_FILE !== $hook_extra['plugin'] ) {
			return $response;
		}

		global $wp_filesystem;

		$install_dir = $result['destination'];
		$proper_dir  = WP_PLUGIN_DIR . '/site-manager-connector';

		// Rename if installed under wrong directory name.
		if ( $install_dir !== $proper_dir ) {
			$wp_filesystem->move( $install_dir, $proper_dir );
			$result['destination'] = $proper_dir;
		}

		// Re-activate if it was active before the update.
		if ( is_plugin_active( self::PLUGIN_FILE ) ) {
			activate_plugin( self::PLUGIN_FILE );
		}

		// Clear cached update data.
		delete_transient( self::TRANSIENT_KEY );

		return $response;
	}
}
