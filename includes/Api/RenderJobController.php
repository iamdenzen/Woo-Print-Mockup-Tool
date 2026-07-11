<?php

namespace WooPrintMockupTool\Api;

use WooPrintMockupTool\Services\RenderPipeline;
use WooPrintMockupTool\Services\ApiKeyAuthenticator;
use WooPrintMockupTool\Services\ProductIdentifierResolver;
use WP_Error;
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

	public function can_render( WP_REST_Request $request ): true|WP_Error {
		return ( new ApiKeyAuthenticator() )->authenticate( $request );
	}

	public function create( WP_REST_Request $request ): WP_REST_Response {
		$files = $request->get_file_params();

		$job_id      = sanitize_text_field( (string) $request->get_param( 'job_id' ) );
		
		$webhook_url = esc_url_raw(
			(string) $request->get_param(
				'webhook_url'
			)
		);

		$identifiers = $this->parse_products(
			$request
		);

		$resolution = (
			new ProductIdentifierResolver()
		)->resolve_many( $identifiers );

		if ( ! empty( $resolution['errors'] ) ) {
			return new WP_REST_Response(
				[
					'success' => false,
					'status'  => 'error',
					'error'   => __(
						'One or more products could not be resolved.',
						'woo-print-mockup-tool'
					),
					'products' => $resolution['errors'],
				],
				400
			);
		}

		if (
			empty( $files['artwork_file'] )
			&& ! empty( $files['logo_file'] )
		) {
			$files['artwork_file'] = $files['logo_file'];
		}

		if ( empty( $files['artwork_file'] ) ) {
			return new WP_REST_Response(
				[
					'success' => false,
					'status'  => 'error',
					'error'   => __(
						'Artwork file is required.',
						'woo-print-mockup-tool'
					),
				],
				400
			);
		}


		$result = ( new RenderPipeline() )->run_api_job(
			$job_id,
			$files['artwork_file'],
			$resolution['product_ids'],
			$webhook_url
		);

		$status_code = 400;

		if (
			! empty( $result['idempotent'] )
			&& 'processing'
			=== ( $result['status'] ?? '' )
		) {
			$status_code = 202;
		} elseif (
			! empty( $result['success'] )
			|| 'partial'
			=== ( $result['status'] ?? '' )
		) {
			$status_code = 200;
		}

		return new WP_REST_Response(
			$result,
			$status_code
		);
	}

	private function parse_products(
		WP_REST_Request $request
	): array {
		$raw = $request->get_param( 'products' );

		if ( null === $raw || '' === $raw ) {
			$raw = $request->get_param(
				'product_ids'
			);
		}

		if ( is_array( $raw ) ) {
			return array_values( $raw );
		}

		if ( is_string( $raw ) ) {
			$decoded = json_decode(
				wp_unslash( $raw ),
				true
			);

			if ( is_array( $decoded ) ) {
				return array_values( $decoded );
			}

			return array_values(
				array_filter(
					array_map(
						'trim',
						explode( ',', $raw )
					),
					static fn( string $value ): bool =>
						'' !== $value
				)
			);
		}

		return [];
	}
}