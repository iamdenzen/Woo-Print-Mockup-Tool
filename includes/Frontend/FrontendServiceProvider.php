<?php

namespace WooPrintMockupTool\Frontend;

use WooPrintMockupTool\Services\ProductConfigRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FrontendServiceProvider {
	public function register(): void {
		add_action( 'woocommerce_single_product_summary', [ $this, 'render_uploader' ], 35 );
		add_shortcode( 'wpmt', [ $this, 'render_uploader_shortcode' ] );
	}

	/**
	 * Hooked directly into woocommerce_single_product_summary — echoes output.
	 */
	public function render_uploader(): void {
		echo $this->get_uploader_html();
	}

	/**
	 * Shortcode callback — must return a string, not echo.
	 *
	 * @param array|string $atts
	 * @param string|null  $content
	 * @param string       $tag
	 */
	public function render_uploader_shortcode( $atts = [], ?string $content = null, string $tag = '' ): string {
		return $this->get_uploader_html();
	}

	/**
	 * Shared rendering logic, buffered so it can be returned or echoed.
	 */
	private function get_uploader_html(): string {
		global $product;

		if ( ! $product ) {
			return '';
		}

		$product_id = (int) $product->get_id();
		$config     = ( new ProductConfigRepository() )->get_by_product_id( $product_id );

		if ( ! $config || empty( $config['enabled'] ) ) {
			return '';
		}

		ob_start();
		include WPMT_PLUGIN_DIR . 'templates/frontend/artwork-uploader.php';
		return (string) ob_get_clean();
	}
}