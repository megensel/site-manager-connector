<?php
/**
 * Themes endpoint: list, install, activate, update, delete.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Site_Manager_Connector_Endpoint_Themes {

	/**
	 * GET /themes
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public static function list_themes( $request ) {
		$themes  = wp_get_themes();
		$updates = get_site_transient( 'update_themes' );
		$current = get_stylesheet();
		$list    = array();

		foreach ( $themes as $stylesheet => $theme ) {
			$update_info = isset( $updates->response[ $stylesheet ] ) ? $updates->response[ $stylesheet ] : null;
			$list[] = array(
				'stylesheet'     => $stylesheet,
				'name'           => $theme->get( 'Name' ),
				'version'        => $theme->get( 'Version' ),
				'description'    => $theme->get( 'Description' ),
				'author'         => $theme->get( 'Author' ),
				'status'         => ( $stylesheet === $current ) ? 'active' : 'inactive',
				'update_version' => $update_info ? $update_info['new_version'] : null,
			);
		}

		return new WP_REST_Response( array( 'themes' => $list ), 200 );
	}

	/**
	 * POST /themes/install
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function install( $request ) {
		$slug = $request->get_param( 'slug' );
		if ( ! function_exists( 'themes_api' ) ) {
			require_once ABSPATH . 'wp-admin/includes/theme.php';
		}
		if ( ! class_exists( 'WP_Upgrader' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		}

		$api = themes_api( 'theme_information', array( 'slug' => $slug ) );
		if ( is_wp_error( $api ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => $api->get_error_message() ), 400 );
		}

		$upgrader = new Theme_Upgrader( new WP_Ajax_Upgrader_Skin() );
		$result   = $upgrader->install( $api->download_link );
		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => $result->get_error_message() ), 400 );
		}
		if ( ! $result ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => __( 'Installation failed.', 'site-manager-connector' ) ), 400 );
		}
		return new WP_REST_Response( array( 'ok' => true, 'message' => __( 'Theme installed.', 'site-manager-connector' ) ), 200 );
	}

	/**
	 * POST /themes/{stylesheet} - action: activate, update, delete
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function action( $request ) {
		$stylesheet = $request->get_param( 'stylesheet' );
		$action     = $request->get_param( 'action' );
		if ( empty( $action ) ) {
			$body = $request->get_json_params();
			$action = isset( $body['action'] ) ? $body['action'] : '';
		}

		$theme = wp_get_theme( $stylesheet );
		if ( ! $theme->exists() ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => __( 'Theme not found.', 'site-manager-connector' ) ), 404 );
		}

		switch ( $action ) {
			case 'activate':
				switch_theme( $stylesheet );
				return new WP_REST_Response( array( 'ok' => true, 'message' => __( 'Theme activated.', 'site-manager-connector' ) ), 200 );
			case 'update':
				if ( ! class_exists( 'WP_Upgrader' ) ) {
					require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
				}
				$upgrader = new Theme_Upgrader( new WP_Ajax_Upgrader_Skin() );
				$result   = $upgrader->upgrade( $stylesheet );
				if ( is_wp_error( $result ) ) {
					return new WP_REST_Response( array( 'ok' => false, 'message' => $result->get_error_message() ), 400 );
				}
				return new WP_REST_Response( array( 'ok' => true, 'message' => __( 'Theme updated.', 'site-manager-connector' ) ), 200 );
			case 'delete':
				if ( get_stylesheet() === $stylesheet ) {
					return new WP_REST_Response( array( 'ok' => false, 'message' => __( 'Switch to another theme before deleting.', 'site-manager-connector' ) ), 400 );
				}
				$result = delete_theme( $stylesheet );
				if ( is_wp_error( $result ) ) {
					return new WP_REST_Response( array( 'ok' => false, 'message' => $result->get_error_message() ), 400 );
				}
				return new WP_REST_Response( array( 'ok' => true, 'message' => __( 'Theme deleted.', 'site-manager-connector' ) ), 200 );
			default:
				return new WP_REST_Response( array( 'ok' => false, 'message' => __( 'Invalid action.', 'site-manager-connector' ) ), 400 );
		}
	}
}
