<?php
/**
 * Updates endpoint: pending core/plugin/theme updates, apply core update.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Site_Manager_Connector_Endpoint_Updates {

	/**
	 * GET /updates
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public static function get( $request ) {
		wp_version_check();
		wp_update_plugins();
		wp_update_themes();

		$core_update = get_site_transient( 'update_core' );
		$core        = array(
			'version'     => get_bloginfo( 'version' ),
			'update_available' => false,
			'new_version' => null,
		);
		if ( isset( $core_update->updates[0] ) ) {
			$u = $core_update->updates[0];
			if ( $u->response === 'upgrade' ) {
				$core['update_available'] = true;
				$core['new_version']      = $u->version;
			}
		}

		$plugin_updates = get_site_transient( 'update_plugins' );
		$plugins        = array();
		if ( isset( $plugin_updates->response ) && is_array( $plugin_updates->response ) ) {
			foreach ( $plugin_updates->response as $file => $update ) {
				$plugins[] = array(
					'file'        => $file,
					'name'        => isset( $update->name ) ? $update->name : $file,
					'new_version' => $update->new_version,
				);
			}
		}

		$theme_updates = get_site_transient( 'update_themes' );
		$themes        = array();
		if ( isset( $theme_updates->response ) && is_array( $theme_updates->response ) ) {
			foreach ( $theme_updates->response as $stylesheet => $update ) {
				$themes[] = array(
					'stylesheet'  => $stylesheet,
					'name'        => isset( $update['theme'] ) ? $update['theme'] : $stylesheet,
					'new_version' => $update['new_version'],
				);
			}
		}

		return new WP_REST_Response( array(
			'core'    => $core,
			'plugins' => $plugins,
			'themes'  => $themes,
		), 200 );
	}

	/**
	 * POST /updates/core - Apply WordPress core update.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function update_core( $request ) {
		wp_version_check();
		$core = get_site_transient( 'update_core' );
		if ( ! isset( $core->updates[0] ) || $core->updates[0]->response !== 'upgrade' ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => __( 'No core update available.', 'site-manager-connector' ) ), 400 );
		}
		require_once ABSPATH . 'wp-admin/includes/class-core-upgrader.php';
		$upgrader = new Core_Upgrader( new WP_Ajax_Upgrader_Skin() );
		$result   = $upgrader->upgrade( $core->updates[0] );
		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => $result->get_error_message() ), 400 );
		}
		return new WP_REST_Response( array( 'ok' => true, 'message' => __( 'WordPress updated.', 'site-manager-connector' ) ), 200 );
	}
}
