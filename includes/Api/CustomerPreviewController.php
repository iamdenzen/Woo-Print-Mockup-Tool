<?php

namespace WooPrintMockupTool\Api;

use WooPrintMockupTool\Services\RenderPipeline;
use WooPrintMockupTool\Services\PreviewRateLimiter;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CustomerPreviewController {
	private string $namespace = 'wpmt/v1';

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/preview',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create' ],
				'permission_callback' => [ $this, 'can_preview' ],
			]
		);
	}

	public function can_preview(
		WP_REST_Request $request
	): true|WP_Error {
		$nonce = $request->get_header(
			'x-wp-nonce'
		);

		if (
			'' === $nonce
			|| ! wp_verify_nonce(
				$nonce,
				'wp_rest'
			)
		) {
			return new WP_Error(
				'wpmt_invalid_rest_nonce',
				__(
					'Preview request could not be verified.',
					'woo-print-mockup-tool'
				),
				[
					'status' => 403,
				]
			);
		}

		return true;
	}

	public function create( WP_REST_Request $request ): WP_REST_Response {
		$files      = $request->get_file_params();
		$product_id = absint( $request->get_param( 'product_id' ) );

		if ( ! $product_id || ! wc_get_product( $product_id ) ) {
			return new WP_REST_Response(
				[
					'status' => 'error',
					'error'  => __(
						'Valid product ID is required.',
						'woo-print-mockup-tool'
					),
				],
				400
			);
		}

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

		$rate_limit = ( new PreviewRateLimiter() )->consume( $request );

		if ( is_wp_error( $rate_limit ) ) {
			$status = $rate_limit->get_error_data();

			return new WP_REST_Response(
				[
					'status' => 'error',
					'error'  => $rate_limit->get_error_message(),
				],
				absint( $status['status'] ?? 429 )
			);
		}

		$result = ( new RenderPipeline() )->run_api_job(
			'preview-' . wp_generate_uuid4(),
			$files['artwork_file'],
			[ $product_id ],
			''
		);

		return new WP_REST_Response(
			$result,
			! empty( $result['results'][0]['success'] ) ? 200 : 400
		);
	}
}