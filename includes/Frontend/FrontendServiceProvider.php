<?php

namespace WooPrintMockupTool\Frontend;

use WooPrintMockupTool\Services\ProductConfigRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FrontendServiceProvider {
	public function register(): void {
		add_action( 'woocommerce_single_product_summary', [ $this, 'render_uploader' ], 35 );
		add_shortcode( 'wpmt', [ $this, 'render_uploader' ], 35 );
	}

	public function render_uploader(): void {
		global $product;

		if ( ! $product ) {
			return;
		}

		$product_id = (int) $product->get_id();
		$config     = ( new ProductConfigRepository() )->get_by_product_id( $product_id );

		if ( ! $config || empty( $config['enabled'] ) ) {
			return;
		}

		include WPMT_PLUGIN_DIR . 'templates/frontend/artwork-uploader.php';
	}
}