<?php
/**
 * Maintenance endpoint: maintenance mode, cache flush, database optimize.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Site_Manager_Connector_Endpoint_Maintenance {

	/**
	 * POST /maintenance/on - Enable maintenance mode.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public static function on( $request ) {
		$content = $request->get_param( 'content' );
		if ( empty( $content ) ) {
			$content = '<?php $upgrading = time(); ?>';
		}
		$file = ABSPATH . '.maintenance';
		$result = file_put_contents( $file, $content );
		if ( $result === false ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => __( 'Could not create .maintenance file.', 'site-manager-connector' ) ), 500 );
		}
		return new WP_REST_Response( array( 'ok' => true, 'message' => __( 'Maintenance mode enabled.', 'site-manager-connector' ) ), 200 );
	}

	/**
	 * POST /maintenance/off - Disable maintenance mode.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public static function off( $request ) {
		$file = ABSPATH . '.maintenance';
		if ( file_exists( $file ) ) {
			unlink( $file );
		}
		return new WP_REST_Response( array( 'ok' => true, 'message' => __( 'Maintenance mode disabled.', 'site-manager-connector' ) ), 200 );
	}

	/**
	 * POST /cache/flush - Flush object cache and rewrite rules.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public static function flush_cache( $request ) {
		wp_cache_flush();
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( 'default' );
		}
		flush_rewrite_rules();
		return new WP_REST_Response( array( 'ok' => true, 'message' => __( 'Cache flushed.', 'site-manager-connector' ) ), 200 );
	}

	/**
	 * POST /database/optimize - Optimize all database tables.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public static function optimize( $request ) {
		global $wpdb;
		$tables = $wpdb->get_results( 'SHOW TABLES', ARRAY_N );
		foreach ( $tables as $row ) {
			$table = $row[0];
			$wpdb->query( "OPTIMIZE TABLE `{$table}`" );
		}
		return new WP_REST_Response( array( 'ok' => true, 'message' => __( 'Database optimized.', 'site-manager-connector' ) ), 200 );
	}
}
