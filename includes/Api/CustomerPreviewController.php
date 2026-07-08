<?php

namespace WooPrintMockupTool\Api;

use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CustomerPreviewController {
	private string $namespace = 'wpmt/v1';

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/preview', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'create' ],
			'permission_callback' => '__return_true',
		] );
	}

	public function create( \WP_REST_Request $request ): \WP_REST_Response {
		return rest_ensure_response( [
			'status'  => 'not_implemented',
			'message' => 'Customer preview endpoint placeholder.',
		] );
	}
}
