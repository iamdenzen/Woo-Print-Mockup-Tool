<?php

namespace WooPrintMockupTool\Assets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AssetsServiceProvider {
	public function register(): void {
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_assets' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'frontend_assets' ] );
	}

	public function admin_assets(): void {
		wp_register_script( 'wpmt-admin', WPMT_PLUGIN_URL . 'assets/admin/js/admin.js', [ 'jquery' ], WPMT_VERSION, true );
		wp_register_style( 'wpmt-admin', WPMT_PLUGIN_URL . 'assets/admin/css/admin.css', [], WPMT_VERSION );
	}

	public function frontend_assets(): void {
		wp_register_script( 'wpmt-frontend', WPMT_PLUGIN_URL . 'assets/frontend/js/frontend.js', [], WPMT_VERSION, true );
		wp_register_style( 'wpmt-frontend', WPMT_PLUGIN_URL . 'assets/frontend/css/frontend.css', [], WPMT_VERSION );
	}
}
