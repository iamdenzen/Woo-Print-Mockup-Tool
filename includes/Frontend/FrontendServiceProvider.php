<?php

namespace WooPrintMockupTool\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FrontendServiceProvider {
	public function register(): void {
		add_action( 'woocommerce_single_product_summary', [ $this, 'render_uploader' ], 35 );
	}

	public function render_uploader(): void {
		global $product;

		if ( ! $product ) {
			return;
		}

		include WPMT_PLUGIN_DIR . 'templates/frontend/artwork-uploader.php';
	}
}
