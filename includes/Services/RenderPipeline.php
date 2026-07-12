<?php

namespace WooPrintMockupTool\Services;

use WooPrintMockupTool\Renderer\RendererFactory;
use WooPrintMockupTool\Storage\UploadDirectories;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RenderPipeline {
	private ProductConfigRepository $configs;
	private RenderJobRepository $jobs;
	private ArtworkUploadService $uploads;
	private RendererFactory $renderer_factory;

	public function __construct() {
		$this->configs          = new ProductConfigRepository();
		$this->jobs             = new RenderJobRepository();
		$this->uploads          = new ArtworkUploadService();
		$this->renderer_factory = new RendererFactory();
	}

	public function run_api_job( string $job_id, array $artwork_file, array $product_ids, string $webhook_url = '' ): array {
		$job_id      = sanitize_text_field( $job_id );
		$product_ids = $this->sanitize_product_ids( $product_ids );
		$webhook_url = esc_url_raw( $webhook_url );

		if ( '' === $job_id ) {
			return [
				'success' => false,
				'status'  => 'error',
				'error'   => __(
					'Job ID is required.',
					'woo-print-mockup-tool'
				),
			];
		}

		$existing_job = $this->jobs
			->get_job_by_job_id( $job_id );

		if ( $existing_job ) {
			return $this->build_existing_job_response(
				$existing_job
			);
		}

		if ( empty( $product_ids ) ) {
			return [
				'success' => false,
				'status'  => 'error',
				'error'   => __(
					'At least one product is required.',
					'woo-print-mockup-tool'
				),
			];
		}

		$max_products = absint( get_option( 'wpmt_max_products_per_job', 10 ) );

		if ( $max_products < 1 ) {
			$max_products = 10;
		}

		if ( count( $product_ids ) > $max_products ) {
			return [
				'success' => false,
				'status'  => 'error',
				'error'   => sprintf(
					/* translators: %d: max products */
					__(
						'Maximum %d products are allowed per render job.',
						'woo-print-mockup-tool'
					),
					$max_products
				),
			];
		}

		$upload = $this->uploads->handle_upload( $artwork_file, 'api' );

		if ( empty( $upload['success'] ) ) {
			return $upload;
		}

		$created = $this->jobs->create_job(
			[
				'job_id'       => $job_id,
				'source'       => 'api',
				'status'       => 'processing',
				'artwork_path' => $upload['path'],
				'webhook_url'  => $webhook_url,
			]
		);

		if ( ! $created ) {
			$this->delete_uploaded_artwork(
				(string) $upload['path']
			);

			/*
			 * Handles concurrent duplicate requests where another
			 * process created the same unique job_id first.
			 */
			$existing_job = $this->jobs
				->get_job_by_job_id( $job_id );

			if ( $existing_job ) {
				return $this->build_existing_job_response(
					$existing_job
				);
			}

			return [
				'success' => false,
				'status'  => 'error',
				'error'   => __(
					'Could not create render job.',
					'woo-print-mockup-tool'
				),
			];
		}

		UploadDirectories::ensure();

		$output_dir = UploadDirectories::job_results_dir( $job_id );
		wp_mkdir_p( $output_dir );

		$renderer = $this->renderer_factory->make();
		$results  = [];

		foreach ( $product_ids as $product_id ) {
			$result = $this->render_product_for_job(
				$renderer,
				$job_id,
				$product_id,
				$upload['path'],
				$output_dir
			);

			$results[] = $result;
		}
		
		$status = $this->determine_job_status(
			$results
		);

		$this->jobs->update_job_status(
			$job_id,
			$status
		);

		$response = [
			'success' => 'completed' === $status,
			'job_id'  => $job_id,
			'status'  => $this->format_job_status( $status ),
			'results' => $results,
		];

		if ( '' !== $webhook_url ) {
			$webhook = (
				new WebhookService()
			)->deliver( $job_id );

			$response['webhook'] = [
				'delivered' => ! empty(
					$webhook['success']
				),
				'attempts'  => absint(
					$webhook['attempts'] ?? 0
				),
			];
		}

		return $response;
	}


	private function build_existing_job_response(
		array $job
	): array {
		$status = sanitize_key(
			$job['status'] ?? ''
		);

		if ( 'processing' === $status ) {
			return [
				'success'    => true,
				'idempotent' => true,
				'job_id'     => (string) $job['job_id'],
				'status'     => 'processing',
				'results'    => [],
			];
		}

		$results = $this->format_stored_results(
			$this->jobs->get_results_by_job_id(
				(string) $job['job_id']
			)
		);

		return [
			'success'    => 'completed' === $status,
			'idempotent' => true,
			'job_id'     => (string) $job['job_id'],
			'status'     => $this->format_job_status(
				$status
			),
			'results'    => $results,
		];
	}


	private function render_product_for_job( $renderer, string $job_id, int $product_id, string $artwork_path, string $output_dir ): array {
		$product = wc_get_product( $product_id );
		$sku     = $product
			? $product->get_sku()
			: '';

		$config = $this->configs->get_by_product_id(
			$product_id
		);

		if (
			! $config
			|| empty( $config['enabled'] )
		) {
			return $this->store_error_result(
				$job_id,
				$product_id,
				$sku,
				__(
					'Product is not configured for mockup rendering.',
					'woo-print-mockup-tool'
				)
			);
		}

		$mockup_path = $this->get_mockup_image_path( $config, $product_id );

		if ( '' === $mockup_path ) {
			return $this->store_error_result(
				$job_id,
				$product_id,
				$sku,
				__(
					'Mockup image could not be found.',
					'woo-print-mockup-tool'
				)
			);
		}

		$output_filename = sanitize_file_name( $product_id . '-' . wp_generate_uuid4() . '.png' );
		$output_path     = trailingslashit( $output_dir ) . $output_filename;
		$output_url      = trailingslashit( UploadDirectories::base_url() ) . 'results/jobs/' . rawurlencode( $job_id ) . '/' . rawurlencode( $output_filename );

		$render_result = $renderer->render(
			[
				'product_id'     => $product_id,
				'mockup_path'    => $mockup_path,
				'artwork_path'   => $artwork_path,
				'placement_data' => is_array( $config['placement_data'] ?? null ) ? $config['placement_data'] : [],
				'render_mode'    => sanitize_key( $config['render_mode'] ?? 'color' ),
				'output_path'    => $output_path,
			]
		);

		if ( empty( $render_result['success'] ) ) {
			return $this->store_error_result(
				$job_id,
				$product_id,
				$sku,
				$render_result['error']
					?? __( 'Render failed.', 'woo-print-mockup-tool' )
			);
		}

		$this->jobs->add_result(
			[
				'job_id'     => $job_id,
				'product_id' => $product_id,
				'image_path' => $output_path,
				'image_url'  => $output_url,
				'status'     => 'success',
			]
		);

		return [
			'success'    => true,
			'product_id' => $product_id,
			'sku'        => $sku,
			'image_url'  => $output_url,
		];
	}

	private function store_error_result( string $job_id, int $product_id, string $sku, string $error ): array {
		$this->jobs->add_result(
			[
				'job_id'        => $job_id,
				'product_id'    => $product_id,
				'status'        => 'error',
				'error_message' => $error,
			]
		);

		return [
			'success'    => false,
			'product_id' => $product_id,
			'sku'		 =>	$sku,
			'error'      => $error,
		];
	}


	private function format_stored_results(
		array $results
	): array {
		$formatted = [];

		foreach ( $results as $result ) {
			$product_id = absint(
				$result['product_id'] ?? 0
			);

			$product = $product_id
				? wc_get_product( $product_id )
				: false;

			$item = [
				'success'    => 'success'
					=== ( $result['status'] ?? '' ),
				'product_id' => $product_id,
				'sku'        => $product
					? $product->get_sku()
					: '',
			];

			if ( ! empty( $result['image_url'] ) ) {
				$item['image_url'] = (string) $result['image_url'];
			}

			if ( ! empty( $result['error_message'] ) ) {
				$item['error'] = (string) $result['error_message'];
			}

			$formatted[] = $item;
		}

		return $formatted;
	}

	private function determine_job_status(
		array $results
	): string {
		$successes = 0;
		$errors    = 0;

		foreach ( $results as $result ) {
			if ( ! empty( $result['success'] ) ) {
				++$successes;
			} else {
				++$errors;
			}
		}

		if ( $successes > 0 && 0 === $errors ) {
			return 'completed';
		}

		if ( $successes > 0 ) {
			return 'partial';
		}

		return 'failed';
	}

	private function format_job_status(
		string $status
	): string {
		return match ( $status ) {
			'completed' => 'success',
			'failed'    => 'error',
			default     => $status,
		};
	}

	private function get_mockup_image_path( array $config, int $product_id ): string {
		$image_id = ! empty( $config['mockup_image_id'] ) ? absint( $config['mockup_image_id'] ) : 0;

		if ( ! $image_id ) {
			$image_id = get_post_thumbnail_id( $product_id );
		}

		if ( ! $image_id ) {
			return '';
		}

		$path = get_attached_file( $image_id );

		return is_string( $path ) && is_readable( $path ) ? $path : '';
	}

	private function sanitize_product_ids( array $product_ids ): array {
		$product_ids = array_map( 'absint', $product_ids );
		$product_ids = array_filter( $product_ids );

		return array_values( array_unique( $product_ids ) );
	}

	private function delete_uploaded_artwork(
		string $path
	): void {
		$path = wp_normalize_path( $path );
		$base = wp_normalize_path(
			UploadDirectories::artwork_dir()
		);

		if ( 0 !== strpos( $path, $base ) ) {
			return;
		}

		if ( is_file( $path ) ) {
			@unlink( $path );
		}
	}
}