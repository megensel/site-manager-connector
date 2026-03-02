<?php
/**
 * REST API registration and auth middleware for Site Manager Connector.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Site_Manager_Connector_Rest_Api {

	const NAMESPACE = 'site-manager/v1';

	/**
	 * Register all REST routes with permission callback that validates API key.
	 */
	public static function register_routes() {
		$permission = array( __CLASS__, 'permission_callback' );

		// Info
		register_rest_route( self::NAMESPACE, '/info', array(
			'methods'             => 'GET',
			'callback'            => array( 'Site_Manager_Connector_Endpoint_Info', 'get' ),
			'permission_callback' => $permission,
		) );

		// Plugins
		register_rest_route( self::NAMESPACE, '/plugins', array(
			'methods'             => 'GET',
			'callback'            => array( 'Site_Manager_Connector_Endpoint_Plugins', 'list_plugins' ),
			'permission_callback' => $permission,
		) );
		register_rest_route( self::NAMESPACE, '/plugins/install', array(
			'methods'             => 'POST',
			'callback'            => array( 'Site_Manager_Connector_Endpoint_Plugins', 'install' ),
			'permission_callback' => $permission,
			'args'                => array(
				'slug' => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		) );
		register_rest_route( self::NAMESPACE, '/plugins/bulk-update', array(
			'methods'             => 'POST',
			'callback'            => array( 'Site_Manager_Connector_Endpoint_Plugins', 'bulk_update' ),
			'permission_callback' => $permission,
		) );
		register_rest_route( self::NAMESPACE, '/plugins/(?P<file>[a-zA-Z0-9_\-\/\.]+)', array(
			'methods'             => 'POST',
			'callback'            => array( 'Site_Manager_Connector_Endpoint_Plugins', 'action' ),
			'permission_callback' => $permission,
			'args'                => array(
				'file'   => array(
					'required' => true,
					'type'     => 'string',
				),
				'action' => array(
					'required' => true,
					'type'     => 'string',
					'enum'     => array( 'activate', 'deactivate', 'update', 'delete' ),
				),
			),
		) );

		// Themes
		register_rest_route( self::NAMESPACE, '/themes', array(
			'methods'             => 'GET',
			'callback'            => array( 'Site_Manager_Connector_Endpoint_Themes', 'list_themes' ),
			'permission_callback' => $permission,
		) );
		register_rest_route( self::NAMESPACE, '/themes/install', array(
			'methods'             => 'POST',
			'callback'            => array( 'Site_Manager_Connector_Endpoint_Themes', 'install' ),
			'permission_callback' => $permission,
			'args'                => array(
				'slug' => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		) );
		register_rest_route( self::NAMESPACE, '/themes/(?P<stylesheet>[a-zA-Z0-9_\-\/\.]+)', array(
			'methods'             => 'POST',
			'callback'            => array( 'Site_Manager_Connector_Endpoint_Themes', 'action' ),
			'permission_callback' => $permission,
			'args'                => array(
				'stylesheet' => array(
					'required' => true,
					'type'     => 'string',
				),
				'action'    => array(
					'required' => true,
					'type'     => 'string',
					'enum'     => array( 'activate', 'update', 'delete' ),
				),
			),
		) );

		// Updates
		register_rest_route( self::NAMESPACE, '/updates', array(
			'methods'             => 'GET',
			'callback'            => array( 'Site_Manager_Connector_Endpoint_Updates', 'get' ),
			'permission_callback' => $permission,
		) );
		register_rest_route( self::NAMESPACE, '/updates/core', array(
			'methods'             => 'POST',
			'callback'            => array( 'Site_Manager_Connector_Endpoint_Updates', 'update_core' ),
			'permission_callback' => $permission,
		) );

		// Backups
		register_rest_route( self::NAMESPACE, '/backups', array(
			'methods'             => 'GET',
			'callback'            => array( 'Site_Manager_Connector_Endpoint_Backups', 'list_backups' ),
			'permission_callback' => $permission,
		) );
		register_rest_route( self::NAMESPACE, '/backups/database', array(
			'methods'             => 'POST',
			'callback'            => array( 'Site_Manager_Connector_Endpoint_Backups', 'create_database' ),
			'permission_callback' => $permission,
		) );
		register_rest_route( self::NAMESPACE, '/backups/files', array(
			'methods'             => 'POST',
			'callback'            => array( 'Site_Manager_Connector_Endpoint_Backups', 'create_files' ),
			'permission_callback' => $permission,
		) );
		register_rest_route( self::NAMESPACE, '/backups/download/(?P<token>[a-zA-Z0-9_\-]+)', array(
			'methods'             => 'GET',
			'callback'            => array( 'Site_Manager_Connector_Endpoint_Backups', 'download' ),
			'permission_callback' => $permission,
			'args'                => array(
				'token' => array(
					'required' => true,
					'type'     => 'string',
				),
			),
		) );

		// Users
		register_rest_route( self::NAMESPACE, '/users', array(
			'methods'             => 'GET',
			'callback'            => array( 'Site_Manager_Connector_Endpoint_Users', 'list_users' ),
			'permission_callback' => $permission,
		) );

		// Options
		register_rest_route( self::NAMESPACE, '/options', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( 'Site_Manager_Connector_Endpoint_Options', 'get' ),
				'permission_callback' => $permission,
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( 'Site_Manager_Connector_Endpoint_Options', 'update' ),
				'permission_callback' => $permission,
			),
		) );

		// Maintenance
		register_rest_route( self::NAMESPACE, '/maintenance/on', array(
			'methods'             => 'POST',
			'callback'            => array( 'Site_Manager_Connector_Endpoint_Maintenance', 'on' ),
			'permission_callback' => $permission,
		) );
		register_rest_route( self::NAMESPACE, '/maintenance/off', array(
			'methods'             => 'POST',
			'callback'            => array( 'Site_Manager_Connector_Endpoint_Maintenance', 'off' ),
			'permission_callback' => $permission,
		) );
		register_rest_route( self::NAMESPACE, '/cache/flush', array(
			'methods'             => 'POST',
			'callback'            => array( 'Site_Manager_Connector_Endpoint_Maintenance', 'flush_cache' ),
			'permission_callback' => $permission,
		) );
		register_rest_route( self::NAMESPACE, '/database/optimize', array(
			'methods'             => 'POST',
			'callback'            => array( 'Site_Manager_Connector_Endpoint_Maintenance', 'optimize' ),
			'permission_callback' => $permission,
		) );

		// Files
		register_rest_route( self::NAMESPACE, '/files', array(
			'methods'             => 'GET',
			'callback'            => array( 'Site_Manager_Connector_Endpoint_Files', 'list_dir' ),
			'permission_callback' => $permission,
			'args'                => array(
				'path' => array(
					'required' => true,
					'type'     => 'string',
				),
			),
		) );
		register_rest_route( self::NAMESPACE, '/files/read', array(
			'methods'             => 'GET',
			'callback'            => array( 'Site_Manager_Connector_Endpoint_Files', 'read' ),
			'permission_callback' => $permission,
			'args'                => array(
				'path' => array(
					'required' => true,
					'type'     => 'string',
				),
			),
		) );
		register_rest_route( self::NAMESPACE, '/files/write', array(
			'methods'             => 'POST',
			'callback'            => array( 'Site_Manager_Connector_Endpoint_Files', 'write' ),
			'permission_callback' => $permission,
			'args'                => array(
				'path'    => array(
					'required' => true,
					'type'     => 'string',
				),
				'content'  => array(
					'type' => 'string',
				),
			),
		) );

		// Security
		register_rest_route( self::NAMESPACE, '/security', array(
			'methods'             => 'GET',
			'callback'            => array( 'Site_Manager_Connector_Endpoint_Security', 'get' ),
			'permission_callback' => $permission,
		) );
	}

	/**
	 * Permission callback: validate X-Site-Manager-Key header.
	 *
	 * @param WP_REST_Request $request
	 * @return bool|WP_Error
	 */
	public static function permission_callback( $request ) {
		$key = $request->get_header( 'X-Site-Manager-Key' );
		if ( empty( $key ) ) {
			$key = $request->get_param( 'api_key' ); // Allow query param for GET download URLs if needed
		}
		if ( ! Site_Manager_Connector_Api_Key::validate( $key ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Invalid or missing API key. Use X-Site-Manager-Key header.', 'site-manager-connector' ),
				array( 'status' => 401 )
			);
		}
		return true;
	}
}
