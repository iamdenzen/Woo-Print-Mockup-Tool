<?php

namespace WooPrintMockupTool\Api;

use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RenderJobController {
	private string $namespace = 'wpmt/v1';

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/render-job', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'create' ],
			'permission_callback' => [ $this, 'can_render' ],
		] );
	}

	public function can_render(): bool {
		// Replace with API key/auth check.
		return current_user_can( 'manage_woocommerce' );
	}

	public function create( \WP_REST_Request $request ): \WP_REST_Response {
		return rest_ensure_response( [
			'status'  => 'not_implemented',
			'message' => 'Render job endpoint placeholder.',
		] );
	}
}
