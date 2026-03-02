<?php
/**
 * Plugin Name: Site Manager Connector
 * Description: REST API for the Site Manager app. Exposes plugin/theme management, backups, updates, and maintenance via API key authentication.
 * Version: 1.0.0
 * Author: Site Manager
 * License: GPL v2 or later
 * Text Domain: site-manager-connector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SITE_MANAGER_CONNECTOR_VERSION', '1.0.0' );
define( 'SITE_MANAGER_CONNECTOR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

class Site_Manager_Connector_Plugin {
	const CRON_HOOK = 'site_manager_connector_cleanup_backups';
	public static function schedule_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'hourly', self::CRON_HOOK );
		}
	}
	public static function unschedule_cron() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}
}

require_once SITE_MANAGER_CONNECTOR_PLUGIN_DIR . 'includes/class-api-key.php';
require_once SITE_MANAGER_CONNECTOR_PLUGIN_DIR . 'includes/class-rest-api.php';
require_once SITE_MANAGER_CONNECTOR_PLUGIN_DIR . 'includes/endpoints/class-info.php';
require_once SITE_MANAGER_CONNECTOR_PLUGIN_DIR . 'includes/endpoints/class-plugins.php';
require_once SITE_MANAGER_CONNECTOR_PLUGIN_DIR . 'includes/endpoints/class-themes.php';
require_once SITE_MANAGER_CONNECTOR_PLUGIN_DIR . 'includes/endpoints/class-updates.php';
require_once SITE_MANAGER_CONNECTOR_PLUGIN_DIR . 'includes/endpoints/class-backups.php';
require_once SITE_MANAGER_CONNECTOR_PLUGIN_DIR . 'includes/endpoints/class-users.php';
require_once SITE_MANAGER_CONNECTOR_PLUGIN_DIR . 'includes/endpoints/class-options.php';
require_once SITE_MANAGER_CONNECTOR_PLUGIN_DIR . 'includes/endpoints/class-maintenance.php';
require_once SITE_MANAGER_CONNECTOR_PLUGIN_DIR . 'includes/endpoints/class-files.php';
require_once SITE_MANAGER_CONNECTOR_PLUGIN_DIR . 'includes/endpoints/class-security.php';

register_activation_hook( __FILE__, array( 'Site_Manager_Connector_Api_Key', 'activate' ) );
register_activation_hook( __FILE__, array( 'Site_Manager_Connector_Plugin', 'schedule_cron' ) );
register_deactivation_hook( __FILE__, array( 'Site_Manager_Connector_Plugin', 'unschedule_cron' ) );

add_action( 'rest_api_init', array( 'Site_Manager_Connector_Rest_Api', 'register_routes' ) );
add_action( 'site_manager_connector_cleanup_backups', array( 'Site_Manager_Connector_Endpoint_Backups', 'cleanup_old_backups' ) );

if ( is_admin() ) {
	require_once SITE_MANAGER_CONNECTOR_PLUGIN_DIR . 'admin/class-settings-page.php';
	add_action( 'admin_menu', array( 'Site_Manager_Connector_Settings_Page', 'add_menu' ) );
	add_action( 'admin_init', array( 'Site_Manager_Connector_Settings_Page', 'register_settings' ) );
}
