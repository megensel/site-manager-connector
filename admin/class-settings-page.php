<?php
/**
 * WP Admin settings page: view and regenerate API key.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Site_Manager_Connector_Settings_Page {

	const OPTION_GROUP = 'site_manager_connector_settings';
	const PAGE_SLUG    = 'site-manager-connector';

	/**
	 * Add Settings > Site Manager menu.
	 */
	public static function add_menu() {
		add_options_page(
			__( 'Site Manager', 'site-manager-connector' ),
			__( 'Site Manager', 'site-manager-connector' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Register settings (API key is stored via Api_Key class, we only need nonce for regenerate).
	 */
	public static function register_settings() {
		if ( isset( $_POST['site_manager_regenerate_key'] ) && check_admin_referer( 'site_manager_regenerate_key' ) && current_user_can( 'manage_options' ) ) {
			$new_key = Site_Manager_Connector_Api_Key::regenerate();
			add_settings_error(
				self::OPTION_GROUP,
				'regenerated',
				__( 'API key has been regenerated. Update your Site Manager app with the new key.', 'site-manager-connector' ),
				'success'
			);
		}

		// Handle update URL save/clear.
		if ( isset( $_POST['site_manager_save_update_url'] ) && check_admin_referer( 'site_manager_update_url' ) && current_user_can( 'manage_options' ) ) {
			$new_url = isset( $_POST['site_manager_update_url_value'] ) ? esc_url_raw( wp_unslash( $_POST['site_manager_update_url_value'] ) ) : '';
			if ( empty( $new_url ) ) {
				delete_option( Site_Manager_Connector_Updater::OPTION_UPDATE_URL );
			} else {
				update_option( Site_Manager_Connector_Updater::OPTION_UPDATE_URL, $new_url );
			}
			// Clear cached update data so the new URL takes effect immediately.
			delete_transient( Site_Manager_Connector_Updater::TRANSIENT_KEY );
			add_settings_error(
				self::OPTION_GROUP,
				'update_url_saved',
				__( 'Update server URL saved.', 'site-manager-connector' ),
				'success'
			);
		}
	}

	/**
	 * Render the settings page.
	 */
	public static function render_page() {
		$api_key = Site_Manager_Connector_Api_Key::get();
		$rest_url = rest_url( 'site-manager/v1/info' );
		wp_enqueue_style( 'site-manager-admin', plugin_dir_url( dirname( __FILE__ ) ) . 'admin/css/admin.css', array(), SITE_MANAGER_CONNECTOR_VERSION );
		?>
		<div class="wrap site-manager-settings">
			<h1><?php esc_html_e( 'Site Manager Connector', 'site-manager-connector' ); ?></h1>
			<?php settings_errors( self::OPTION_GROUP ); ?>
			<p class="description">
				<?php esc_html_e( 'Use this API key in your Site Manager app to manage this WordPress site remotely (plugins, themes, backups, updates).', 'site-manager-connector' ); ?>
			</p>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'API Key', 'site-manager-connector' ); ?></th>
					<td>
						<code class="site-manager-api-key"><?php echo esc_html( $api_key ); ?></code>
						<form method="post" style="display:inline-block; margin-left: 1em;">
							<?php wp_nonce_field( 'site_manager_regenerate_key' ); ?>
							<input type="submit" name="site_manager_regenerate_key" class="button button-secondary" value="<?php esc_attr_e( 'Regenerate key', 'site-manager-connector' ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Regenerating will invalidate the current key. Update your Site Manager app with the new key. Continue?', 'site-manager-connector' ) ); ?>');" />
						</form>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'REST API base', 'site-manager-connector' ); ?></th>
					<td><code><?php echo esc_html( $rest_url ); ?></code></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Connector Version', 'site-manager-connector' ); ?></th>
					<td><code><?php echo esc_html( SITE_MANAGER_CONNECTOR_VERSION ); ?></code></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Update Server URL', 'site-manager-connector' ); ?></th>
					<td>
						<form method="post">
							<?php wp_nonce_field( 'site_manager_update_url' ); ?>
							<input type="text" name="site_manager_update_url_value" value="<?php echo esc_attr( get_option( Site_Manager_Connector_Updater::OPTION_UPDATE_URL, '' ) ); ?>" class="regular-text" placeholder="<?php echo esc_attr( Site_Manager_Connector_Updater::DEFAULT_UPDATE_URL ); ?>" />
							<input type="submit" name="site_manager_save_update_url" class="button button-secondary" value="<?php esc_attr_e( 'Save', 'site-manager-connector' ); ?>" />
							<p class="description"><?php esc_html_e( 'Leave empty to use the default GitHub update URL.', 'site-manager-connector' ); ?></p>
						</form>
					</td>
				</tr>
			</table>
			<p class="description">
				<?php esc_html_e( 'In the Site Manager app, add this site and paste the API key above. The app will send the key in the X-Site-Manager-Key header.', 'site-manager-connector' ); ?>
			</p>
		</div>
		<?php
	}
}
