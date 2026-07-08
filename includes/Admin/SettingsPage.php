<?php

namespace WooPrintMockupTool\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SettingsPage {
	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
	}

	public function add_menu_page(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Woo Print Mockup Tool', 'woo-print-mockup-tool' ),
			__( 'Print Mockup Tool', 'woo-print-mockup-tool' ),
			'manage_woocommerce',
			'wpmt-settings',
			[ $this, 'render' ]
		);
	}

	public function render(): void {
		include WPMT_PLUGIN_DIR . 'templates/admin/settings.php';
	}
}
