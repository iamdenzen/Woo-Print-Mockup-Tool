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

		if ( '' === $job_id ) {
			return [
				'success' => false,
				'error'   => __( 'Job ID is required.', 'woo-print-mockup-tool' ),
			];
		}

		if ( empty( $product_ids ) ) {
			return [
				'success' => false,
				'error'   => __( 'At least one product ID is required.', 'woo-print-mockup-tool' ),
			];
		}

		$max_products = absint( get_option( 'wpmt_max_products_per_job', 10 ) );

		if ( $max_products < 1 ) {
			$max_products = 10;
		}

		if ( count( $product_ids ) > $max_products ) {
			return [
				'success' => false,
				'error'   => sprintf(
					/* translators: %d: max products per render job */
					__( 'Maximum %d products are allowed per render job.', 'woo-print-mockup-tool' ),
					$max_products
				),
			];
		}

		$upload = $this->uploads->handle_upload( $artwork_file, 'api' );

		if ( empty( $upload['success'] ) ) {
			return $upload;
		}

		$this->jobs->create_job(
			[
				'job_id'       => $job_id,
				'source'       => 'api',
				'status'       => 'processing',
				'artwork_path' => $upload['path'],
				'webhook_url'  => $webhook_url,
			]
		);

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

		$has_errors = false;

		foreach ( $results as $result ) {
			if ( empty( $result['success'] ) ) {
				$has_errors = true;
				break;
			}
		}

		$this->jobs->update_job_status( $job_id, $has_errors ? 'partial' : 'completed' );

		return [
			'success' => ! $has_errors,
			'job_id'  => $job_id,
			'status'  => $has_errors ? 'partial' : 'success',
			'results' => $results,
		];
	}

	private function render_product_for_job( $renderer, string $job_id, int $product_id, string $artwork_path, string $output_dir ): array {
		$config = $this->configs->get_by_product_id( $product_id );

		if ( ! $config || empty( $config['enabled'] ) ) {
			return $this->store_error_result(
				$job_id,
				$product_id,
				__( 'Product is not configured for mockup rendering.', 'woo-print-mockup-tool' )
			);
		}

		$mockup_path = $this->get_mockup_image_path( $config, $product_id );

		if ( '' === $mockup_path ) {
			return $this->store_error_result(
				$job_id,
				$product_id,
				__( 'Mockup image could not be found.', 'woo-print-mockup-tool' )
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
				$render_result['error'] ?? __( 'Render failed.', 'woo-print-mockup-tool' )
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
			'image_url'  => $output_url,
		];
	}

	private function store_error_result( string $job_id, int $product_id, string $error ): array {
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
			'error'      => $error,
		];
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
}