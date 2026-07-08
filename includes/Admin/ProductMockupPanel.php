<?php

namespace WooPrintMockupTool\Admin;

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
		include WPMT_PLUGIN_DIR . 'templates/admin/product-mockup-panel.php';
	}

	public function save( int $product_id ): void {
		if ( ! current_user_can( 'edit_product', $product_id ) ) {
			return;
		}

		// Save through a repository/service in the next implementation step.
	}
}
