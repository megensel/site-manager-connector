<?php
/**
 * Options endpoint: read/write WordPress options.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Site_Manager_Connector_Endpoint_Options {

	const ALLOWED_OPTIONS = array(
		'blogname',
		'blogdescription',
		'siteurl',
		'home',
		'admin_email',
		'users_can_register',
		'default_role',
		'permalink_structure',
		'posts_per_page',
		'date_format',
		'time_format',
		'timezone_string',
		'start_of_week',
	);

	/**
	 * GET /options - Return key options.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public static function get( $request ) {
		$keys = $request->get_param( 'keys' );
		if ( is_string( $keys ) ) {
			$keys = array_map( 'trim', explode( ',', $keys ) );
		}
		if ( empty( $keys ) ) {
			$keys = self::ALLOWED_OPTIONS;
		}
		$options = array();
		foreach ( $keys as $key ) {
			$key = sanitize_text_field( $key );
			if ( in_array( $key, self::ALLOWED_OPTIONS, true ) ) {
				$options[ $key ] = get_option( $key, null );
			}
		}
		return new WP_REST_Response( array( 'options' => $options ), 200 );
	}

	/**
	 * POST /options - Update specified options.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public static function update( $request ) {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) || empty( $body['options'] ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => __( 'Provide options object.', 'site-manager-connector' ) ), 400 );
		}
		$updated = array();
		foreach ( $body['options'] as $key => $value ) {
			$key = sanitize_text_field( $key );
			if ( in_array( $key, self::ALLOWED_OPTIONS, true ) ) {
				update_option( $key, $value );
				$updated[ $key ] = $value;
			}
		}
		return new WP_REST_Response( array( 'ok' => true, 'updated' => $updated ), 200 );
	}
}
