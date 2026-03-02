<?php
/**
 * System info endpoint: WP version, PHP version, disk usage, DB size, etc.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Site_Manager_Connector_Endpoint_Info {

	/**
	 * GET /info
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public static function get( $request ) {
		global $wpdb;

		$active_plugins = get_option( 'active_plugins', array() );
		$theme         = wp_get_theme();
		$disk_usage    = self::get_disk_usage();
		$db_size       = self::get_database_size();

		$data = array(
			'connector_version'   => SITE_MANAGER_CONNECTOR_VERSION,
			'wordpress_version'   => get_bloginfo( 'version' ),
			'php_version'         => PHP_VERSION,
			'server_software'     => isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : '',
			'site_url'            => get_site_url(),
			'home_url'            => get_home_url(),
			'is_multisite'        => is_multisite(),
			'active_plugins_count' => count( $active_plugins ),
			'active_theme'        => array(
				'name'    => $theme->get( 'Name' ),
				'version' => $theme->get( 'Version' ),
			),
			'disk_usage_bytes'    => $disk_usage,
			'database_size_bytes' => $db_size,
		);

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Get disk usage of wp-content directory in bytes.
	 *
	 * @return int
	 */
	private static function get_disk_usage() {
		$dir = WP_CONTENT_DIR;
		if ( ! is_dir( $dir ) ) {
			return 0;
		}
		$size = 0;
		foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS | RecursiveDirectoryIterator::FOLLOW_SYMLINKS ) ) as $file ) {
			if ( $file->isFile() ) {
				$size += $file->getSize();
			}
		}
		return $size;
	}

	/**
	 * Get database size in bytes.
	 *
	 * @return int
	 */
	private static function get_database_size() {
		global $wpdb;
		$result = $wpdb->get_var( "SELECT SUM(data_length + index_length) FROM information_schema.TABLES WHERE table_schema = '" . esc_sql( DB_NAME ) . "'" );
		return (int) $result;
	}
}
