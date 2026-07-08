<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wpmt-artwork-uploader" data-product-id="<?php echo esc_attr( $product->get_id() ); ?>">
	<h3><?php esc_html_e( 'Preview your print', 'woo-print-mockup-tool' ); ?></h3>
	<input type="file" accept="image/png,image/jpeg,image/svg+xml" class="wpmt-artwork-file" />
	<button type="button" class="button wpmt-generate-preview">
		<?php esc_html_e( 'Generate preview', 'woo-print-mockup-tool' ); ?>
	</button>
	<div class="wpmt-preview-result"></div>
</div>
