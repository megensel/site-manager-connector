<?php
/**
 * Files endpoint: list directory, read file, write file.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Site_Manager_Connector_Endpoint_Files {

	/**
	 * Get resolved absolute path within ABSPATH or WP_CONTENT_DIR (no escaping above).
	 *
	 * @param string $path Relative path (e.g. wp-content/themes).
	 * @return string|WP_Error Absolute path or error.
	 */
	private static function resolve_path( $path ) {
		$path = sanitize_text_field( $path );
		$path = preg_replace( '#/+#', '/', trim( $path, '/' ) );
		if ( $path === '' || strpos( $path, '..' ) !== false ) {
			return new WP_Error( 'invalid_path', __( 'Invalid path.', 'site-manager-connector' ), array( 'status' => 400 ) );
		}
		$base = ABSPATH;
		$absolute = realpath( $base . $path );
		if ( $absolute === false ) {
			$absolute = realpath( $base ) . '/' . $path;
		}
		$base_real = realpath( $base );
		if ( strpos( $absolute, $base_real ) !== 0 ) {
			return new WP_Error( 'invalid_path', __( 'Path is outside allowed directory.', 'site-manager-connector' ), array( 'status' => 400 ) );
		}
		return $absolute;
	}

	/**
	 * GET /files - List directory contents.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function list_dir( $request ) {
		$path = $request->get_param( 'path' );
		$resolved = self::resolve_path( $path );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}
		if ( ! is_dir( $resolved ) ) {
			return new WP_Error( 'not_directory', __( 'Path is not a directory.', 'site-manager-connector' ), array( 'status' => 400 ) );
		}
		$entries = array();
		$items = scandir( $resolved );
		foreach ( $items as $item ) {
			if ( $item === '.' || $item === '..' ) {
				continue;
			}
			$full = $resolved . '/' . $item;
			$entries[] = array(
				'name'  => $item,
				'is_dir' => is_dir( $full ),
				'size'   => is_file( $full ) ? filesize( $full ) : null,
			);
		}
		return new WP_REST_Response( array( 'path' => $path, 'entries' => $entries ), 200 );
	}

	/**
	 * GET /files/read - Read file contents.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function read( $request ) {
		$path = $request->get_param( 'path' );
		$resolved = self::resolve_path( $path );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}
		if ( ! is_file( $resolved ) ) {
			return new WP_Error( 'not_file', __( 'Path is not a file.', 'site-manager-connector' ), array( 'status' => 400 ) );
		}
		$content = file_get_contents( $resolved );
		if ( $content === false ) {
			return new WP_Error( 'read_failed', __( 'Could not read file.', 'site-manager-connector' ), array( 'status' => 500 ) );
		}
		return new WP_REST_Response( array( 'path' => $path, 'content' => $content ), 200 );
	}

	/**
	 * POST /files/write - Write file contents.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function write( $request ) {
		$path = $request->get_param( 'path' );
		$content = $request->get_param( 'content' );
		if ( $content === null ) {
			$body = $request->get_json_params();
			$content = isset( $body['content'] ) ? $body['content'] : '';
		}
		$resolved = self::resolve_path( $path );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}
		$dir = dirname( $resolved );
		if ( ! is_dir( $dir ) ) {
			if ( ! wp_mkdir_p( $dir ) ) {
				return new WP_Error( 'mkdir_failed', __( 'Could not create directory.', 'site-manager-connector' ), array( 'status' => 500 ) );
			}
		}
		$result = file_put_contents( $resolved, $content );
		if ( $result === false ) {
			return new WP_Error( 'write_failed', __( 'Could not write file.', 'site-manager-connector' ), array( 'status' => 500 ) );
		}
		return new WP_REST_Response( array( 'ok' => true, 'path' => $path ), 200 );
	}
}
