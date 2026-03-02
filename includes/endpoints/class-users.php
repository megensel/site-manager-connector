<?php
/**
 * Users endpoint: list users with roles.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Site_Manager_Connector_Endpoint_Users {

	/**
	 * GET /users
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public static function list_users( $request ) {
		$users = get_users( array( 'orderby' => 'login' ) );
		$list  = array();
		foreach ( $users as $user ) {
			$list[] = array(
				'id'           => (int) $user->ID,
				'login'        => $user->user_login,
				'email'        => $user->user_email,
				'display_name' => $user->display_name,
				'roles'        => array_values( (array) $user->roles ),
			);
		}
		return new WP_REST_Response( array( 'users' => $list ), 200 );
	}
}
