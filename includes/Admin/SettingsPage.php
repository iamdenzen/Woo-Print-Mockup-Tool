<?php

namespace WooPrintMockupTool\Admin;

use WooPrintMockupTool\Storage\UploadDirectories;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SettingsPage {
	private const PAGE_SLUG     = 'wpmt-settings';
	private const OPTION_GROUP  = 'wpmt_settings';

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	public function add_menu_page(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Print Mockup Tool', 'woo-print-mockup-tool' ),
			__( 'Print Mockup Tool', 'woo-print-mockup-tool' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			[ $this, 'render_page' ]
		);
	}

	public function register_settings(): void {
		$this->register_rendering_settings();
		$this->register_api_settings();
		$this->register_remote_renderer_settings();
		$this->register_diagnostics();
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		include WPMT_PLUGIN_DIR . 'templates/admin/settings.php';
	}

	private function register_rendering_settings(): void {
		add_settings_section(
			'wpmt_rendering_section',
			__( 'Rendering', 'woo-print-mockup-tool' ),
			[ $this, 'render_rendering_section_description' ],
			self::PAGE_SLUG
		);

		register_setting(
			self::OPTION_GROUP,
			'wpmt_renderer_engine',
			[
				'type'              => 'string',
				'default'           => 'imagick',
				'sanitize_callback' => [ $this, 'sanitize_renderer_engine' ],
			]
		);

		add_settings_field(
			'wpmt_renderer_engine',
			__( 'Renderer engine', 'woo-print-mockup-tool' ),
			[ $this, 'render_renderer_engine_field' ],
			self::PAGE_SLUG,
			'wpmt_rendering_section'
		);

		register_setting(
			self::OPTION_GROUP,
			'wpmt_max_products_per_job',
			[
				'type'              => 'integer',
				'default'           => 10,
				'sanitize_callback' => [ $this, 'sanitize_max_products' ],
			]
		);

		add_settings_field(
			'wpmt_max_products_per_job',
			__( 'Maximum products per job', 'woo-print-mockup-tool' ),
			[ $this, 'render_max_products_field' ],
			self::PAGE_SLUG,
			'wpmt_rendering_section'
		);

		register_setting(
			self::OPTION_GROUP,
			'wpmt_result_retention_minutes',
			[
				'type'              => 'integer',
				'default'           => 30,
				'sanitize_callback' => [ $this, 'sanitize_retention_minutes' ],
			]
		);

		add_settings_field(
			'wpmt_result_retention_minutes',
			__( 'Result retention', 'woo-print-mockup-tool' ),
			[ $this, 'render_retention_field' ],
			self::PAGE_SLUG,
			'wpmt_rendering_section'
		);

		register_setting(
			self::OPTION_GROUP,
			'wpmt_white_threshold',
			[
				'type'              => 'integer',
				'default'           => 245,
				'sanitize_callback' => [ $this, 'sanitize_white_threshold' ],
			]
		);

		add_settings_field(
			'wpmt_white_threshold',
			__( 'White background threshold', 'woo-print-mockup-tool' ),
			[ $this, 'render_white_threshold_field' ],
			self::PAGE_SLUG,
			'wpmt_rendering_section'
		);

		register_setting(
			self::OPTION_GROUP,
			'wpmt_output_max_dimension',
			[
				'type'              => 'integer',
				'default'           => 1500,
				'sanitize_callback' => [ $this, 'sanitize_output_max_dimension' ],
			]
		);

		add_settings_field(
			'wpmt_output_max_dimension',
			__( 'Maximum output dimension', 'woo-print-mockup-tool' ),
			[ $this, 'render_output_max_dimension_field' ],
			self::PAGE_SLUG,
			'wpmt_rendering_section'
		);
	}

	private function register_api_settings(): void {
		add_settings_section(
			'wpmt_api_section',
			__( 'API', 'woo-print-mockup-tool' ),
			[ $this, 'render_api_section_description' ],
			self::PAGE_SLUG
		);

		register_setting(
			self::OPTION_GROUP,
			'wpmt_api_enabled',
			[
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => [ $this, 'sanitize_checkbox' ],
			]
		);

		add_settings_field(
			'wpmt_api_enabled',
			__( 'External API', 'woo-print-mockup-tool' ),
			[ $this, 'render_api_enabled_field' ],
			self::PAGE_SLUG,
			'wpmt_api_section'
		);
	}

	private function register_remote_renderer_settings(): void {
		add_settings_section(
			'wpmt_remote_renderer_section',
			__( 'Remote Renderer', 'woo-print-mockup-tool' ),
			[ $this, 'render_remote_renderer_section_description' ],
			self::PAGE_SLUG
		);

		register_setting(
			self::OPTION_GROUP,
			'wpmt_remote_renderer_url',
			[
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'esc_url_raw',
			]
		);

		add_settings_field(
			'wpmt_remote_renderer_url',
			__( 'Service URL', 'woo-print-mockup-tool' ),
			[ $this, 'render_remote_renderer_url_field' ],
			self::PAGE_SLUG,
			'wpmt_remote_renderer_section'
		);

		register_setting(
			self::OPTION_GROUP,
			'wpmt_remote_renderer_token',
			[
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			]
		);

		add_settings_field(
			'wpmt_remote_renderer_token',
			__( 'Authentication token', 'woo-print-mockup-tool' ),
			[ $this, 'render_remote_renderer_token_field' ],
			self::PAGE_SLUG,
			'wpmt_remote_renderer_section'
		);

		register_setting(
			self::OPTION_GROUP,
			'wpmt_remote_renderer_timeout',
			[
				'type'              => 'integer',
				'default'           => 30,
				'sanitize_callback' => [ $this, 'sanitize_remote_timeout' ],
			]
		);

		add_settings_field(
			'wpmt_remote_renderer_timeout',
			__( 'Request timeout', 'woo-print-mockup-tool' ),
			[ $this, 'render_remote_renderer_timeout_field' ],
			self::PAGE_SLUG,
			'wpmt_remote_renderer_section'
		);
	}

	private function register_diagnostics(): void {
		add_settings_section(
			'wpmt_diagnostics_section',
			__( 'Diagnostics', 'woo-print-mockup-tool' ),
			[ $this, 'render_diagnostics' ],
			self::PAGE_SLUG
		);
	}

	public function render_rendering_section_description(): void {
		echo '<p>';
		esc_html_e(
			'Configure how artwork previews are rendered and stored.',
			'woo-print-mockup-tool'
		);
		echo '</p>';
	}

	public function render_api_section_description(): void {
		echo '<p>';
		esc_html_e(
			'Control access to the external render-job API.',
			'woo-print-mockup-tool'
		);
		echo '</p>';
	}

	public function render_remote_renderer_section_description(): void {
		echo '<p>';
		esc_html_e(
			'These settings are used when the Remote Service renderer is selected.',
			'woo-print-mockup-tool'
		);
		echo '</p>';
	}

	public function render_renderer_engine_field(): void {
		$value = get_option( 'wpmt_renderer_engine', 'imagick' );
		?>
		<select name="wpmt_renderer_engine" id="wpmt_renderer_engine">
			<option value="imagick" <?php selected( $value, 'imagick' ); ?>>
				<?php esc_html_e( 'Local Imagick', 'woo-print-mockup-tool' ); ?>
			</option>

			<option value="remote" <?php selected( $value, 'remote' ); ?>>
				<?php esc_html_e( 'Remote Service', 'woo-print-mockup-tool' ); ?>
			</option>
		</select>

		<p class="description">
			<?php esc_html_e( 'Choose the engine used to generate mockup previews.', 'woo-print-mockup-tool' ); ?>
		</p>
		<?php
	}

	public function render_max_products_field(): void {
		$value = absint( get_option( 'wpmt_max_products_per_job', 10 ) );
		?>
		<input
			type="number"
			name="wpmt_max_products_per_job"
			id="wpmt_max_products_per_job"
			value="<?php echo esc_attr( $value ); ?>"
			min="1"
			max="100"
		/>

		<p class="description">
			<?php esc_html_e( 'Maximum number of products accepted in one render job.', 'woo-print-mockup-tool' ); ?>
		</p>
		<?php
	}

	public function render_retention_field(): void {
		$value = absint( get_option( 'wpmt_result_retention_minutes', 30 ) );
		?>
		<input
			type="number"
			name="wpmt_result_retention_minutes"
			id="wpmt_result_retention_minutes"
			value="<?php echo esc_attr( $value ); ?>"
			min="5"
			max="10080"
		/>

		<span>
			<?php esc_html_e( 'minutes', 'woo-print-mockup-tool' ); ?>
		</span>

		<p class="description">
			<?php esc_html_e( 'Generated previews are eligible for deletion after this time.', 'woo-print-mockup-tool' ); ?>
		</p>
		<?php
	}

	public function render_white_threshold_field(): void {
		$value = absint( get_option( 'wpmt_white_threshold', 245 ) );
		?>
		<input
			type="number"
			name="wpmt_white_threshold"
			id="wpmt_white_threshold"
			value="<?php echo esc_attr( $value ); ?>"
			min="0"
			max="255"
		/>

		<p class="description">
			<?php esc_html_e( 'RGB values equal to or above this threshold are treated as white.', 'woo-print-mockup-tool' ); ?>
		</p>
		<?php
	}

	public function render_output_max_dimension_field(): void {
		$value = absint( get_option( 'wpmt_output_max_dimension', 1500 ) );
		?>
		<input
			type="number"
			name="wpmt_output_max_dimension"
			id="wpmt_output_max_dimension"
			value="<?php echo esc_attr( $value ); ?>"
			min="300"
			max="5000"
		/>

		<span>
			<?php esc_html_e( 'pixels', 'woo-print-mockup-tool' ); ?>
		</span>

		<p class="description">
			<?php esc_html_e( 'Maximum width or height of generated web preview images.', 'woo-print-mockup-tool' ); ?>
		</p>
		<?php
	}

	public function render_api_enabled_field(): void {
		$value = (bool) get_option( 'wpmt_api_enabled', false );
		?>
		<label for="wpmt_api_enabled">
			<input
				type="checkbox"
				name="wpmt_api_enabled"
				id="wpmt_api_enabled"
				value="1"
				<?php checked( $value ); ?>
			/>

			<?php esc_html_e( 'Enable authenticated external render requests.', 'woo-print-mockup-tool' ); ?>
		</label>

		<p class="description">
			<?php esc_html_e( 'API key management will appear here after the authentication layer is enabled.', 'woo-print-mockup-tool' ); ?>
		</p>
		<?php
	}

	public function render_remote_renderer_url_field(): void {
		$value = (string) get_option( 'wpmt_remote_renderer_url', '' );
		?>
		<input
			type="url"
			name="wpmt_remote_renderer_url"
			id="wpmt_remote_renderer_url"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
			placeholder="https://renderer.example.com"
		/>
		<?php
	}

	public function render_remote_renderer_token_field(): void {
		$value = (string) get_option( 'wpmt_remote_renderer_token', '' );
		?>
		<input
			type="password"
			name="wpmt_remote_renderer_token"
			id="wpmt_remote_renderer_token"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
			autocomplete="new-password"
		/>
		<?php
	}

	public function render_remote_renderer_timeout_field(): void {
		$value = absint( get_option( 'wpmt_remote_renderer_timeout', 30 ) );
		?>
		<input
			type="number"
			name="wpmt_remote_renderer_timeout"
			id="wpmt_remote_renderer_timeout"
			value="<?php echo esc_attr( $value ); ?>"
			min="5"
			max="300"
		/>

		<span>
			<?php esc_html_e( 'seconds', 'woo-print-mockup-tool' ); ?>
		</span>
		<?php
	}

	public function render_diagnostics(): void {
		$imagick_available = class_exists( '\Imagick' );
		$upload_writable   = is_dir( UploadDirectories::base_dir() )
			&& is_writable( UploadDirectories::base_dir() );
		$cleanup_scheduled = (bool) wp_next_scheduled( 'wpmt_cleanup_expired_mockups' );

		$imagick_version = '';

		if ( $imagick_available ) {
			try {
				$version = \Imagick::getVersion();

				if ( is_array( $version ) && ! empty( $version['versionString'] ) ) {
					$imagick_version = (string) $version['versionString'];
				}
			} catch ( \Throwable $e ) {
				$imagick_version = '';
			}
		}
		?>
		<table class="widefat striped wpmt-diagnostics-table">
			<tbody>
				<tr>
					<td>
						<strong><?php esc_html_e( 'Imagick', 'woo-print-mockup-tool' ); ?></strong>
					</td>
					<td>
						<?php echo esc_html( $imagick_available ? __( 'Available', 'woo-print-mockup-tool' ) : __( 'Not available', 'woo-print-mockup-tool' ) ); ?>
					</td>
				</tr>

				<?php if ( $imagick_version ) : ?>
					<tr>
						<td>
							<strong><?php esc_html_e( 'Imagick version', 'woo-print-mockup-tool' ); ?></strong>
						</td>
						<td>
							<?php echo esc_html( $imagick_version ); ?>
						</td>
					</tr>
				<?php endif; ?>

				<tr>
					<td>
						<strong><?php esc_html_e( 'Upload directory', 'woo-print-mockup-tool' ); ?></strong>
					</td>
					<td>
						<?php echo esc_html( $upload_writable ? __( 'Writable', 'woo-print-mockup-tool' ) : __( 'Not writable', 'woo-print-mockup-tool' ) ); ?>
					</td>
				</tr>

				<tr>
					<td>
						<strong><?php esc_html_e( 'Cleanup cron', 'woo-print-mockup-tool' ); ?></strong>
					</td>
					<td>
						<?php echo esc_html( $cleanup_scheduled ? __( 'Scheduled', 'woo-print-mockup-tool' ) : __( 'Not scheduled', 'woo-print-mockup-tool' ) ); ?>
					</td>
				</tr>

				<tr>
					<td>
						<strong><?php esc_html_e( 'Render endpoint', 'woo-print-mockup-tool' ); ?></strong>
					</td>
					<td>
						<code><?php echo esc_html( rest_url( 'wpmt/v1/render-job' ) ); ?></code>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	public function sanitize_renderer_engine( mixed $value ): string {
		$value = sanitize_key( (string) $value );

		return in_array( $value, [ 'imagick', 'remote' ], true )
			? $value
			: 'imagick';
	}

	public function sanitize_max_products( mixed $value ): int {
		return max( 1, min( 100, absint( $value ) ) );
	}

	public function sanitize_retention_minutes( mixed $value ): int {
		return max( 5, min( 10080, absint( $value ) ) );
	}

	public function sanitize_white_threshold( mixed $value ): int {
		return max( 0, min( 255, absint( $value ) ) );
	}

	public function sanitize_output_max_dimension( mixed $value ): int {
		return max( 300, min( 5000, absint( $value ) ) );
	}

	public function sanitize_checkbox( mixed $value ): bool {
		return ! empty( $value );
	}

	public function sanitize_remote_timeout( mixed $value ): int {
		return max( 5, min( 300, absint( $value ) ) );
	}
}