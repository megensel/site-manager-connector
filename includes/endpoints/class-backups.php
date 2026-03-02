<?php
/**
 * Backups endpoint: DB dump, file archive, token-based download, list, cleanup.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Site_Manager_Connector_Endpoint_Backups {

	const OPTION_TOKENS = 'site_manager_backup_tokens';
	const BACKUP_DIR   = 'sm-backups';
	const TOKEN_EXPIRY  = 3600;   // 1 hour
	const FILE_MAX_AGE  = 86400;  // 24 hours

	/**
	 * Get the backup directory path (under wp-content).
	 *
	 * @return string
	 */
	private static function get_backup_dir() {
		$dir = WP_CONTENT_DIR . '/' . self::BACKUP_DIR;
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		return $dir;
	}

	/**
	 * Generate a unique token and store it with file path and expiry.
	 *
	 * @param string $relative_path Path relative to wp-content.
	 * @return string Token.
	 */
	private static function create_download_token( $relative_path ) {
		$token = bin2hex( random_bytes( 16 ) );
		$tokens = get_option( self::OPTION_TOKENS, array() );
		$tokens[ $token ] = array(
			'path'   => $relative_path,
			'expires' => time() + self::TOKEN_EXPIRY,
		);
		update_option( self::OPTION_TOKENS, $tokens );
		return $token;
	}

	/**
	 * Consume token and return file path; remove token (one-time use).
	 *
	 * @param string $token
	 * @return string|false Full path to file or false if invalid/expired.
	 */
	private static function consume_token( $token ) {
		$tokens = get_option( self::OPTION_TOKENS, array() );
		if ( ! isset( $tokens[ $token ] ) ) {
			return false;
		}
		$data = $tokens[ $token ];
		if ( $data['expires'] < time() ) {
			unset( $tokens[ $token ] );
			update_option( self::OPTION_TOKENS, $tokens );
			return false;
		}
		$full_path = WP_CONTENT_DIR . '/' . $data['path'];
		if ( ! is_file( $full_path ) ) {
			unset( $tokens[ $token ] );
			update_option( self::OPTION_TOKENS, $tokens );
			return false;
		}
		unset( $tokens[ $token ] );
		update_option( self::OPTION_TOKENS, $tokens );
		return $full_path;
	}

	/**
	 * GET /backups - List available backup files.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public static function list_backups( $request ) {
		$dir = self::get_backup_dir();
		$list = array();
		$now = time();
		$files = glob( $dir . '/*' );
		foreach ( $files as $file ) {
			if ( ! is_file( $file ) ) {
				continue;
			}
			$mtime = filemtime( $file );
			$age = $now - $mtime;
			if ( $age > self::FILE_MAX_AGE ) {
				continue;
			}
			$list[] = array(
				'name' => basename( $file ),
				'size_bytes' => filesize( $file ),
				'created_at' => $mtime,
			);
		}
		usort( $list, function ( $a, $b ) {
			return $b['created_at'] - $a['created_at'];
		} );
		return new WP_REST_Response( array( 'backups' => $list ), 200 );
	}

	/**
	 * POST /backups/database - Create DB export, return download token.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_database( $request ) {
		global $wpdb;
		$dir = self::get_backup_dir();
		$filename = 'database-' . gmdate( 'Y-m-d-His' ) . '-' . bin2hex( random_bytes( 4 ) ) . '.sql';
		$full_path = $dir . '/' . $filename;
		$relative = self::BACKUP_DIR . '/' . $filename;

		$tables = $wpdb->get_results( 'SHOW TABLES', ARRAY_N );
		$sql = "-- Site Manager Connector DB export\n-- " . gmdate( 'c' ) . "\n\n";
		$sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

		foreach ( $tables as $row ) {
			$table = $row[0];
			$create = $wpdb->get_row( "SHOW CREATE TABLE `{$table}`", ARRAY_N );
			if ( $create ) {
				$sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
				$sql .= $create[1] . ";\n\n";
			}
			$results = $wpdb->get_results( "SELECT * FROM `{$table}`", ARRAY_A );
			if ( empty( $results ) ) {
				continue;
			}
			$keys = array_keys( $results[0] );
			$cols = array_map( function ( $k ) {
				return '`' . str_replace( '`', '``', $k ) . '`';
			}, $keys );
			$sql .= "INSERT INTO `{$table}` (" . implode( ',', $cols ) . ") VALUES\n";
			$rows = array();
			foreach ( $results as $row ) {
				$vals = array();
				foreach ( $row as $v ) {
					$vals[] = $wpdb->prepare( '%s', $v );
				}
				$rows[] = '(' . implode( ',', $vals ) . ')';
			}
			$sql .= implode( ",\n", $rows ) . ";\n\n";
		}
		$sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

		if ( file_put_contents( $full_path, $sql ) === false ) {
			return new WP_Error( 'write_failed', __( 'Could not write backup file.', 'site-manager-connector' ), array( 'status' => 500 ) );
		}
		$token = self::create_download_token( $relative );
		return new WP_REST_Response( array(
			'ok'    => true,
			'token' => $token,
			'size_bytes' => filesize( $full_path ),
			'expires_in' => self::TOKEN_EXPIRY,
		), 200 );
	}

	/**
	 * POST /backups/files - Create zip of wp-content, return download token.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_files( $request ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'zip_unavailable', __( 'ZipArchive is not available.', 'site-manager-connector' ), array( 'status' => 500 ) );
		}
		$content_dir = WP_CONTENT_DIR;
		$dir = self::get_backup_dir();
		$filename = 'files-' . gmdate( 'Y-m-d-His' ) . '-' . bin2hex( random_bytes( 4 ) ) . '.zip';
		$full_path = $dir . '/' . $filename;
		$relative = self::BACKUP_DIR . '/' . $filename;

		$zip = new ZipArchive();
		if ( $zip->open( $full_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) {
			return new WP_Error( 'zip_failed', __( 'Could not create zip file.', 'site-manager-connector' ), array( 'status' => 500 ) );
		}
		$base_len = strlen( $content_dir ) + 1;
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $content_dir, RecursiveDirectoryIterator::SKIP_DOTS | RecursiveDirectoryIterator::FOLLOW_SYMLINKS )
		);
		foreach ( $iterator as $file ) {
			if ( ! $file->isFile() ) {
				continue;
			}
			$path = $file->getPathname();
			$relative_path = substr( $path, $base_len );
			if ( strpos( $relative_path, self::BACKUP_DIR . '/' ) === 0 ) {
				continue;
			}
			$zip->addFile( $path, 'wp-content/' . $relative_path );
		}
		$zip->close();
		$token = self::create_download_token( $relative );
		return new WP_REST_Response( array(
			'ok'    => true,
			'token' => $token,
			'size_bytes' => filesize( $full_path ),
			'expires_in' => self::TOKEN_EXPIRY,
		), 200 );
	}

	/**
	 * GET /backups/download/{token} - Stream file and remove token.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function download( $request ) {
		$token = $request->get_param( 'token' );
		$full_path = self::consume_token( $token );
		if ( ! $full_path ) {
			return new WP_Error( 'invalid_token', __( 'Invalid or expired download token.', 'site-manager-connector' ), array( 'status' => 404 ) );
		}
		$filename = basename( $full_path );
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename="' . esc_attr( $filename ) . '"' );
		header( 'Content-Length: ' . filesize( $full_path ) );
		readfile( $full_path );
		@unlink( $full_path );
		exit;
	}

	/**
	 * Cron: delete backup files older than FILE_MAX_AGE and prune expired tokens.
	 */
	public static function cleanup_old_backups() {
		$dir = self::get_backup_dir();
		$cutoff = time() - self::FILE_MAX_AGE;
		$files = glob( $dir . '/*' );
		foreach ( $files as $file ) {
			if ( is_file( $file ) && filemtime( $file ) < $cutoff ) {
				@unlink( $file );
			}
		}
		$tokens = get_option( self::OPTION_TOKENS, array() );
		$now = time();
		foreach ( $tokens as $token => $data ) {
			if ( $data['expires'] < $now ) {
				unset( $tokens[ $token ] );
			}
		}
		update_option( self::OPTION_TOKENS, $tokens );
	}
}
