<?php

namespace WooPrintMockupTool\Api;

use WooPrintMockupTool\Services\RenderPipeline;
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
				'permission_callback' => '__return_true',
			]
		);
	}

	public function create( WP_REST_Request $request ): WP_REST_Response {
		$files      = $request->get_file_params();
		$product_id = absint( $request->get_param( 'product_id' ) );

		if ( ! $product_id ) {
			return new WP_REST_Response(
				[
					'status' => 'error',
					'error'  => __( 'Product ID is required.', 'woo-print-mockup-tool' ),
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