<?php
/**
 * Security heuristic checks endpoint for Site Manager Connector.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Site_Manager_Connector_Endpoint_Security {

	/**
	 * Return security-related heuristic data about this WordPress installation.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public static function get( $request ) {
		global $wpdb;

		// Debug settings
		$wp_debug         = defined( 'WP_DEBUG' ) && WP_DEBUG;
		$wp_debug_log     = defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG;
		$wp_debug_display = defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY;

		// Database prefix
		$db_table_prefix = $wpdb->prefix;

		// Security constants
		$disallow_file_edit = defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT;
		$force_ssl_admin    = defined( 'FORCE_SSL_ADMIN' ) && FORCE_SSL_ADMIN;

		// Auto-updates
		$auto_updates_enabled = true;
		if ( defined( 'WP_AUTO_UPDATE_CORE' ) ) {
			$auto_updates_enabled = WP_AUTO_UPDATE_CORE !== false && WP_AUTO_UPDATE_CORE !== 'false';
		}

		// File permissions
		$wp_config_path  = ABSPATH . 'wp-config.php';
		$htaccess_path   = ABSPATH . '.htaccess';
		$wp_content_path = WP_CONTENT_DIR;

		$file_permissions = array(
			'wp_config'  => file_exists( $wp_config_path ) ? substr( sprintf( '%o', fileperms( $wp_config_path ) ), -4 ) : null,
			'htaccess'   => file_exists( $htaccess_path ) ? substr( sprintf( '%o', fileperms( $htaccess_path ) ), -4 ) : null,
			'wp_content' => is_dir( $wp_content_path ) ? substr( sprintf( '%o', fileperms( $wp_content_path ) ), -4 ) : null,
		);

		// Admin user check
		$admin_user_exists  = ( get_user_by( 'login', 'admin' ) !== false );
		$admin_users_count  = count( get_users( array( 'role' => 'administrator', 'fields' => 'ID' ) ) );

		// SSL
		$is_ssl = is_ssl();

		// Versions
		$php_version = phpversion();
		$wp_version  = get_bloginfo( 'version' );

		// Latest WP version from update transient
		$wp_latest_version = null;
		$update_core       = get_site_transient( 'update_core' );
		if ( $update_core && ! empty( $update_core->updates ) ) {
			foreach ( $update_core->updates as $update ) {
				if ( 'latest' === $update->response || 'upgrade' === $update->response ) {
					$wp_latest_version = $update->version;
					break;
				}
			}
		}

		// XML-RPC
		$xmlrpc_enabled = apply_filters( 'xmlrpc_enabled', true );

		// Directory listing protection
		$directory_listing = false;
		if ( file_exists( $htaccess_path ) ) {
			$htaccess_content = file_get_contents( $htaccess_path );
			if ( $htaccess_content !== false ) {
				$directory_listing = ( strpos( $htaccess_content, 'Options -Indexes' ) !== false );
			}
		}

		return rest_ensure_response( array(
			'wp_debug'              => $wp_debug,
			'wp_debug_log'          => $wp_debug_log,
			'wp_debug_display'      => $wp_debug_display,
			'db_table_prefix'       => $db_table_prefix,
			'disallow_file_edit'    => $disallow_file_edit,
			'force_ssl_admin'       => $force_ssl_admin,
			'auto_updates_enabled'  => $auto_updates_enabled,
			'file_permissions'      => $file_permissions,
			'admin_user_exists'     => $admin_user_exists,
			'admin_users_count'     => $admin_users_count,
			'is_ssl'                => $is_ssl,
			'php_version'           => $php_version,
			'wp_version'            => $wp_version,
			'wp_latest_version'     => $wp_latest_version,
			'xmlrpc_enabled'        => $xmlrpc_enabled,
			'directory_listing'     => $directory_listing,
		) );
	}
}
