<?php

namespace WooPrintMockupTool\Api;

use WooPrintMockupTool\Services\RenderPipeline;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RenderJobController {
	private string $namespace = 'wpmt/v1';

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/render-job',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create' ],
				'permission_callback' => [ $this, 'can_render' ],
			]
		);
	}

	public function can_render(): bool {
		// Temporary admin-only auth for testing. Replace with API key later.
		return current_user_can( 'manage_woocommerce' );
	}

	public function create( WP_REST_Request $request ): WP_REST_Response {
		$files = $request->get_file_params();

		$job_id      = sanitize_text_field( (string) $request->get_param( 'job_id' ) );
		$product_ids = $this->parse_product_ids( $request->get_param( 'product_ids' ) );
		$webhook_url = esc_url_raw( (string) $request->get_param( 'webhook_url' ) );

		if ( empty( $files['artwork_file'] ) && ! empty( $files['logo_file'] ) ) {
			$files['artwork_file'] = $files['logo_file'];
		}

		if ( empty( $files['artwork_file'] ) ) {
			return new WP_REST_Response(
				[
					'status' => 'error',
					'error'  => __( 'Artwork file is required.', 'woo-print-mockup-tool' ),
				],
				400
			);
		}

		$result = ( new RenderPipeline() )->run_api_job(
			$job_id,
			$files['artwork_file'],
			$product_ids,
			$webhook_url
		);

		return new WP_REST_Response(
			$result,
			! empty( $result['success'] ) ? 200 : 400
		);
	}

	private function parse_product_ids( mixed $raw ): array {
		if ( is_array( $raw ) ) {
			return array_values( array_filter( array_map( 'absint', $raw ) ) );
		}

		if ( is_string( $raw ) ) {
			$decoded = json_decode( wp_unslash( $raw ), true );

			if ( is_array( $decoded ) ) {
				return array_values( array_filter( array_map( 'absint', $decoded ) ) );
			}

			return array_values(
				array_filter(
					array_map(
						'absint',
						array_map( 'trim', explode( ',', $raw ) )
					)
				)
			);
		}

		return [];
	}
}