<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="wpmt_print_mockup_panel" class="panel woocommerce_options_panel hidden">
	<div class="options_group">
		<p class="form-field">
			<label><?php esc_html_e( 'Enable print mockup', 'woo-print-mockup-tool' ); ?></label>
			<input type="checkbox" name="wpmt_enabled" value="1" />
		</p>
		<p class="form-field">
			<label><?php esc_html_e( 'Placement type', 'woo-print-mockup-tool' ); ?></label>
			<select name="wpmt_placement_type">
				<option value="rectangle"><?php esc_html_e( 'Rectangle', 'woo-print-mockup-tool' ); ?></option>
				<option value="perspective"><?php esc_html_e( '4-point perspective', 'woo-print-mockup-tool' ); ?></option>
			</select>
		</p>
		<p class="form-field">
			<label><?php esc_html_e( 'Render mode', 'woo-print-mockup-tool' ); ?></label>
			<select name="wpmt_render_mode">
				<option value="color"><?php esc_html_e( 'Color print', 'woo-print-mockup-tool' ); ?></option>
				<option value="engraving"><?php esc_html_e( 'Engraving', 'woo-print-mockup-tool' ); ?></option>
			</select>
		</p>
		<div class="wpmt-placement-canvas">
			<?php esc_html_e( 'Drawing canvas placeholder.', 'woo-print-mockup-tool' ); ?>
		</div>
	</div>
</div>
