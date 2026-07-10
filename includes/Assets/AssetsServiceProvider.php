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

	public function admin_assets( string $hook_suffix ): void {
		wp_register_script(
			'wpmt-admin',
			WPMT_PLUGIN_URL . 'assets/admin/js/admin.js',
			[ 'jquery', 'media-editor', 'media-views', 'wpmt-fabric' ],
			WPMT_VERSION,
			true
		);

		wp_register_script(
			'wpmt-fabric',
			'https://cdn.jsdelivr.net/npm/fabric@7.4.0/dist/index.min.js',
			[],
			WPMT_VERSION,
			true
		);

		wp_register_style(
			'wpmt-admin',
			WPMT_PLUGIN_URL . 'assets/admin/css/admin.css',
			[],
			WPMT_VERSION
		);

		if ( ! in_array( $hook_suffix, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen || 'product' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script( 'wpmt-fabric' );
		wp_enqueue_script( 'wpmt-admin' );
		wp_enqueue_style( 'wpmt-admin' );
	}

	public function frontend_assets(): void {
		wp_register_script(
			'wpmt-frontend',
			WPMT_PLUGIN_URL . 'assets/frontend/js/frontend.js',
			[],
			WPMT_VERSION,
			true
		);

		wp_register_style(
			'wpmt-frontend',
			WPMT_PLUGIN_URL . 'assets/frontend/css/frontend.css',
			[],
			WPMT_VERSION
		);

		if ( ! is_product() ) {
			return;
		}

		wp_enqueue_script( 'wpmt-frontend' );
		wp_enqueue_style( 'wpmt-frontend' );

		wp_localize_script(
			'wpmt-frontend',
			'WPMTFrontend',
			[
				'restUrl' => esc_url_raw( rest_url( 'wpmt/v1/preview' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
			]
		);
	}
}