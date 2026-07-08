<?php

namespace WooPrintMockupTool\Admin;

use WooPrintMockupTool\Services\ProductConfigRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ProductMockupPanel {
	public function register(): void {
		add_filter( 'woocommerce_product_data_tabs', [ $this, 'add_tab' ] );
		add_action( 'woocommerce_product_data_panels', [ $this, 'render_panel' ] );
		add_action( 'woocommerce_process_product_meta', [ $this, 'save' ] );
	}

	public function add_tab( array $tabs ): array {
		$tabs['wpmt_print_mockup'] = [
			'label'    => __( 'Print Mockup', 'woo-print-mockup-tool' ),
			'target'   => 'wpmt_print_mockup_panel',
			'class'    => [],
			'priority' => 80,
		];

		return $tabs;
	}

	public function render_panel(): void {
		global $post;

		$product_id = $post ? (int) $post->ID : 0;
		$config     = $product_id > 0 ? ( new ProductConfigRepository() )->get_by_product_id( $product_id ) : null;

		include WPMT_PLUGIN_DIR . 'templates/admin/product-mockup-panel.php';
	}

	public function save( int $product_id ): void {
		if ( ! current_user_can( 'edit_product', $product_id ) ) {
			return;
		}

		if (
			empty( $_POST['wpmt_product_mockup_nonce'] ) ||
			! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['wpmt_product_mockup_nonce'] ) ),
				'wpmt_save_product_mockup'
			)
		) {
			return;
		}

		$data = [
			'enabled'         => isset( $_POST['wpmt_enabled'] ) ? 1 : 0,
			'mockup_image_id' => isset( $_POST['wpmt_mockup_image_id'] ) ? absint( $_POST['wpmt_mockup_image_id'] ) : 0,
			'placement_type'  => isset( $_POST['wpmt_placement_type'] )
				? sanitize_key( wp_unslash( $_POST['wpmt_placement_type'] ) )
				: 'rectangle',
			'placement_data'  => isset( $_POST['wpmt_placement_data'] )
				? wp_unslash( $_POST['wpmt_placement_data'] )
				: '',
			'render_mode'     => isset( $_POST['wpmt_render_mode'] )
				? sanitize_key( wp_unslash( $_POST['wpmt_render_mode'] ) )
				: 'color',
		];

		( new ProductConfigRepository() )->upsert( $product_id, $data );
	}
}
