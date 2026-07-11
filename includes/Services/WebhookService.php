<?php

namespace WooPrintMockupTool\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebhookService {
	private const MAX_ATTEMPTS = 3;

	private RenderJobRepository $jobs;

	public function __construct() {
		$this->jobs = new RenderJobRepository();
	}

	public function deliver( string $job_id ): array {
		$job = $this->jobs->get_job_by_job_id( $job_id );

		if ( ! $job ) {
			return [
				'success' => false,
				'error'   => __(
					'Render job could not be found.',
					'woo-print-mockup-tool'
				),
			];
		}

		$webhook_url = esc_url_raw(
			(string) ( $job['webhook_url'] ?? '' )
		);

		if ( '' === $webhook_url ) {
			return [
				'success' => true,
				'skipped' => true,
			];
		}

		if (
			'delivered'
			=== ( $job['webhook_status'] ?? '' )
		) {
			return [
				'success' => true,
				'skipped' => true,
			];
		}

		$attempts = absint(
			$job['webhook_attempts'] ?? 0
		);

		if ( $attempts >= self::MAX_ATTEMPTS ) {
			return [
				'success' => false,
				'error'   => __(
					'Maximum webhook delivery attempts reached.',
					'woo-print-mockup-tool'
				),
			];
		}

		++$attempts;

		$payload = $this->build_payload(
			$job,
			$this->jobs->get_results_by_job_id( $job_id )
		);

		$response = wp_safe_remote_post(
			$webhook_url,
			[
				'timeout'     => 15,
				'redirection' => 3,
				'headers'     => [
					'Content-Type' => 'application/json',
					'User-Agent'   => 'WooPrintMockupTool/' . WPMT_VERSION,
					'X-WPMT-Event' => 'render.completed',
				],
				'body'        => wp_json_encode( $payload ),
			]
		);

		if ( is_wp_error( $response ) ) {
			return $this->handle_failure(
				$job_id,
				$attempts,
				$response->get_error_message()
			);
		}

		$status_code = wp_remote_retrieve_response_code(
			$response
		);

		if (
			$status_code < 200
			|| $status_code >= 300
		) {
			return $this->handle_failure(
				$job_id,
				$attempts,
				sprintf(
					/* translators: %d: HTTP status code */
					__(
						'Webhook returned HTTP %d.',
						'woo-print-mockup-tool'
					),
					$status_code
				)
			);
		}

		$this->jobs->mark_webhook_delivered(
			$job_id,
			$attempts
		);

		return [
			'success'     => true,
			'status_code' => $status_code,
			'attempts'    => $attempts,
		];
	}

	private function handle_failure(
		string $job_id,
		int $attempts,
		string $error
	): array {
		$next_attempt_at = null;

		if ( $attempts < self::MAX_ATTEMPTS ) {
			$delay = $this->get_retry_delay( $attempts );

			$next_attempt_at = current_datetime()
				->modify( '+' . $delay . ' seconds' )
				->format( 'Y-m-d H:i:s' );

			$this->schedule_retry(
				$job_id,
				$delay
			);
		}

		$this->jobs->mark_webhook_failed(
			$job_id,
			$attempts,
			$error,
			$next_attempt_at
		);

		return [
			'success'  => false,
			'error'    => $error,
			'attempts' => $attempts,
		];
	}

	private function schedule_retry(
		string $job_id,
		int $delay
	): void {
		$args = [ $job_id ];

		if (
			wp_next_scheduled(
				'wpmt_retry_webhook',
				$args
			)
		) {
			return;
		}

		wp_schedule_single_event(
			time() + $delay,
			'wpmt_retry_webhook',
			$args
		);
	}

	private function get_retry_delay( int $attempts ): int {
		return 1 === $attempts
			? 5 * MINUTE_IN_SECONDS
			: 15 * MINUTE_IN_SECONDS;
	}

	private function build_payload(
		array $job,
		array $results
	): array {
		$formatted_results = [];

		foreach ( $results as $result ) {
			$product_id = absint(
				$result['product_id'] ?? 0
			);

			$product = $product_id
				? wc_get_product( $product_id )
				: false;

			$item = [
				'product_id' => $product_id,
				'sku'        => $product
					? $product->get_sku()
					: '',
				'success'    => 'success'
					=== ( $result['status'] ?? '' ),
			];

			if ( ! empty( $result['image_url'] ) ) {
				$item['image_url'] = esc_url_raw(
					$result['image_url']
				);
			}

			if ( ! empty( $result['error_message'] ) ) {
				$item['error'] = (
					string
				) $result['error_message'];
			}

			$formatted_results[] = $item;
		}

		return [
			'job_id'  => (string) $job['job_id'],
			'status'  => $this->format_job_status(
				(string) $job['status']
			),
			'results' => $formatted_results,
		];
	}

	private function format_job_status(
		string $status
	): string {
		return match ( $status ) {
			'completed' => 'success',
			'partial'   => 'partial',
			'failed'    => 'error',
			default     => $status,
		};
	}
}