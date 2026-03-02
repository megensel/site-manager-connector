<?php
/**
 * Plugins endpoint: list, install, activate, deactivate, update, delete.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Site_Manager_Connector_Endpoint_Plugins {

	/**
	 * GET /plugins
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public static function list_plugins( $request ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$all_plugins = get_plugins();
		$updates     = get_site_transient( 'update_plugins' );
		$list        = array();

		foreach ( $all_plugins as $file => $plugin_data ) {
			$is_active = is_plugin_active( $file );
			$update    = isset( $updates->response[ $file ] ) ? $updates->response[ $file ] : null;
			$list[]    = array(
				'file'           => $file,
				'name'           => $plugin_data['Name'],
				'version'        => $plugin_data['Version'],
				'description'    => $plugin_data['Description'],
				'author'         => $plugin_data['Author'],
				'status'         => $is_active ? 'active' : 'inactive',
				'update_version' => $update ? $update->new_version : null,
			);
		}

		return new WP_REST_Response( array( 'plugins' => $list ), 200 );
	}

	/**
	 * POST /plugins/install
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function install( $request ) {
		$slug = $request->get_param( 'slug' );
		if ( ! function_exists( 'plugins_api' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		}
		if ( ! class_exists( 'WP_Upgrader' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		}

		$api = plugins_api( 'plugin_information', array( 'slug' => $slug ) );
		if ( is_wp_error( $api ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => $api->get_error_message() ), 400 );
		}

		$upgrader = new Plugin_Upgrader( new WP_Ajax_Upgrader_Skin() );
		$result   = $upgrader->install( $api->download_link );
		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => $result->get_error_message() ), 400 );
		}
		if ( ! $result ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => __( 'Installation failed.', 'site-manager-connector' ) ), 400 );
		}
		return new WP_REST_Response( array( 'ok' => true, 'message' => __( 'Plugin installed.', 'site-manager-connector' ) ), 200 );
	}

	/**
	 * POST /plugins/{file} - action: activate, deactivate, update, delete
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function action( $request ) {
		$file   = $request->get_param( 'file' );
		$action = $request->get_param( 'action' );
		if ( empty( $action ) ) {
			$body = $request->get_json_params();
			$action = isset( $body['action'] ) ? $body['action'] : '';
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all = get_plugins();
		if ( ! isset( $all[ $file ] ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => __( 'Plugin not found.', 'site-manager-connector' ) ), 404 );
		}

		switch ( $action ) {
			case 'activate':
				$result = activate_plugin( $file );
				if ( is_wp_error( $result ) ) {
					return new WP_REST_Response( array( 'ok' => false, 'message' => $result->get_error_message() ), 400 );
				}
				return new WP_REST_Response( array( 'ok' => true, 'message' => __( 'Plugin activated.', 'site-manager-connector' ) ), 200 );
			case 'deactivate':
				deactivate_plugins( $file );
				return new WP_REST_Response( array( 'ok' => true, 'message' => __( 'Plugin deactivated.', 'site-manager-connector' ) ), 200 );
			case 'update':
				if ( ! class_exists( 'WP_Upgrader' ) ) {
					require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
				}
				$upgrader = new Plugin_Upgrader( new WP_Ajax_Upgrader_Skin() );
				$result   = $upgrader->upgrade( $file );
				if ( is_wp_error( $result ) ) {
					return new WP_REST_Response( array( 'ok' => false, 'message' => $result->get_error_message() ), 400 );
				}
				return new WP_REST_Response( array( 'ok' => true, 'message' => __( 'Plugin updated.', 'site-manager-connector' ) ), 200 );
			case 'delete':
				if ( is_plugin_active( $file ) ) {
					return new WP_REST_Response( array( 'ok' => false, 'message' => __( 'Deactivate the plugin before deleting.', 'site-manager-connector' ) ), 400 );
				}
				$result = delete_plugins( array( $file ) );
				if ( is_wp_error( $result ) ) {
					return new WP_REST_Response( array( 'ok' => false, 'message' => $result->get_error_message() ), 400 );
				}
				return new WP_REST_Response( array( 'ok' => true, 'message' => __( 'Plugin deleted.', 'site-manager-connector' ) ), 200 );
			default:
				return new WP_REST_Response( array( 'ok' => false, 'message' => __( 'Invalid action.', 'site-manager-connector' ) ), 400 );
		}
	}

	/**
	 * POST /plugins/bulk-update
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public static function bulk_update( $request ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! class_exists( 'WP_Upgrader' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		}
		wp_update_plugins();
		$updates = get_site_transient( 'update_plugins' );
		$to_update = isset( $updates->response ) ? array_keys( $updates->response ) : array();
		$results = array( 'updated' => array(), 'failed' => array() );
		$upgrader = new Plugin_Upgrader( new WP_Ajax_Upgrader_Skin() );
		foreach ( $to_update as $file ) {
			$result = $upgrader->upgrade( $file );
			if ( is_wp_error( $result ) ) {
				$results['failed'][ $file ] = $result->get_error_message();
			} else {
				$results['updated'][] = $file;
			}
		}
		return new WP_REST_Response( array( 'ok' => true, 'results' => $results ), 200 );
	}
}
