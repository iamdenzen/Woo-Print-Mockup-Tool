<?php

namespace WooPrintMockupTool\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ApiServiceProvider {
	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		( new RenderJobController() )->register_routes();
		( new CustomerPreviewController() )->register_routes();
	}
}
