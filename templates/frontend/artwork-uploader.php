<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$product_id = $product ? (int) $product->get_id() : 0;
?>

<div class="wpmt-artwork-uploader" data-product-id="<?php echo esc_attr( $product_id ); ?>">
	<h3><?php esc_html_e( 'Preview your artwork', 'woo-print-mockup-tool' ); ?></h3>

	<p>
		<?php esc_html_e( 'Upload your logo or artwork to see how it looks on this product.', 'woo-print-mockup-tool' ); ?>
	</p>

	<input
		type="file"
		class="wpmt-artwork-file"
		accept="image/png,image/jpeg"
	/>

	<button type="button" class="button wpmt-generate-preview">
		<?php esc_html_e( 'Generate preview', 'woo-print-mockup-tool' ); ?>
	</button>

	<div class="wpmt-preview-status" aria-live="polite"></div>
	<div class="wpmt-preview-result"></div>
</div>