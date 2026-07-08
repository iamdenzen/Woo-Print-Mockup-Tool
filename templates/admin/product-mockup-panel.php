<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$config = is_array( $config ?? null ) ? $config : [];

$enabled         = ! empty( $config['enabled'] );
$mockup_image_id = ! empty( $config['mockup_image_id'] ) ? absint( $config['mockup_image_id'] ) : 0;
$placement_type  = ! empty( $config['placement_type'] ) ? sanitize_key( $config['placement_type'] ) : 'rectangle';
$render_mode     = ! empty( $config['render_mode'] ) ? sanitize_key( $config['render_mode'] ) : 'color';
$placement_data  = ! empty( $config['placement_data'] ) && is_array( $config['placement_data'] )
	? wp_json_encode( $config['placement_data'] )
	: '{}';
?>

<div id="wpmt_print_mockup_panel" class="panel woocommerce_options_panel hidden">
	<?php wp_nonce_field( 'wpmt_save_product_mockup', 'wpmt_product_mockup_nonce' ); ?>

	<div class="options_group">
		<p class="form-field">
			<label for="wpmt_enabled">
				<?php esc_html_e( 'Enable print mockup', 'woo-print-mockup-tool' ); ?>
			</label>

			<input
				type="checkbox"
				id="wpmt_enabled"
				name="wpmt_enabled"
				value="1"
				<?php checked( $enabled ); ?>
			/>
		</p>

		<p class="form-field">
			<label for="wpmt_mockup_image_id">
				<?php esc_html_e( 'Mockup image ID', 'woo-print-mockup-tool' ); ?>
			</label>

			<input
				type="number"
				id="wpmt_mockup_image_id"
				name="wpmt_mockup_image_id"
				value="<?php echo esc_attr( $mockup_image_id ); ?>"
				min="0"
			/>

			<span class="description">
				<?php esc_html_e( 'Leave empty to use the product featured image later.', 'woo-print-mockup-tool' ); ?>
			</span>
		</p>

		<p class="form-field">
			<label for="wpmt_placement_type">
				<?php esc_html_e( 'Placement type', 'woo-print-mockup-tool' ); ?>
			</label>

			<select id="wpmt_placement_type" name="wpmt_placement_type">
				<option value="rectangle" <?php selected( $placement_type, 'rectangle' ); ?>>
					<?php esc_html_e( 'Rectangle', 'woo-print-mockup-tool' ); ?>
				</option>
				<option value="perspective" <?php selected( $placement_type, 'perspective' ); ?>>
					<?php esc_html_e( '4-point perspective', 'woo-print-mockup-tool' ); ?>
				</option>
			</select>
		</p>

		<p class="form-field">
			<label for="wpmt_render_mode">
				<?php esc_html_e( 'Render mode', 'woo-print-mockup-tool' ); ?>
			</label>

			<select id="wpmt_render_mode" name="wpmt_render_mode">
				<option value="color" <?php selected( $render_mode, 'color' ); ?>>
					<?php esc_html_e( 'Color print', 'woo-print-mockup-tool' ); ?>
				</option>
				<option value="engraving" <?php selected( $render_mode, 'engraving' ); ?>>
					<?php esc_html_e( 'Engraving', 'woo-print-mockup-tool' ); ?>
				</option>
			</select>
		</p>

		<input
			type="hidden"
			id="wpmt_placement_data"
			name="wpmt_placement_data"
			value="<?php echo esc_attr( $placement_data ); ?>"
		/>

		<div class="wpmt-placement-canvas">
			<?php esc_html_e( 'Drawing canvas placeholder.', 'woo-print-mockup-tool' ); ?>
		</div>
	</div>
</div>