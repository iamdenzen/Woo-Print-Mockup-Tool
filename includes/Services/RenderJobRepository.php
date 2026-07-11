<?php

namespace WooPrintMockupTool\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RenderJobRepository {
	private string $jobs_table;
	private string $results_table;

	public function __construct() {
		global $wpdb;

		$this->jobs_table    = $wpdb->prefix . 'wpmt_render_jobs';
		$this->results_table = $wpdb->prefix . 'wpmt_render_results';
	}

	public function create_job( array $data ): bool {
		global $wpdb;

		$now        = gmdate();
		$expires_at = $this->get_expires_at();

		return false !== $wpdb->insert(
			$this->jobs_table,
			[
				'job_id'       => sanitize_text_field( $data['job_id'] ?? '' ),
				'source'       => sanitize_key( $data['source'] ?? 'api' ),
				'status'       => sanitize_key( $data['status'] ?? 'pending' ),
				'artwork_path' => ! empty( $data['artwork_path'] ) ? sanitize_text_field( $data['artwork_path'] ) : null,
				'webhook_url'  => ! empty( $data['webhook_url'] ) ? esc_url_raw( $data['webhook_url'] ) : null,
				'created_at'   => $now,
				'expires_at'   => $expires_at,
			],
			[
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
			]
		);
	}

	public function update_job_status( string $job_id, string $status ): bool {
		global $wpdb;

		return false !== $wpdb->update(
			$this->jobs_table,
			[ 'status' => sanitize_key( $status ) ],
			[ 'job_id' => sanitize_text_field( $job_id ) ],
			[ '%s' ],
			[ '%s' ]
		);
	}

	public function add_result( array $data ): bool {
		global $wpdb;

		$now        = gmdate();
		$expires_at = $this->get_expires_at();

		return false !== $wpdb->insert(
			$this->results_table,
			[
				'job_id'        => sanitize_text_field( $data['job_id'] ?? '' ),
				'session_key'   => ! empty( $data['session_key'] ) ? sanitize_text_field( $data['session_key'] ) : null,
				'product_id'    => absint( $data['product_id'] ?? 0 ),
				'image_path'    => ! empty( $data['image_path'] ) ? sanitize_text_field( $data['image_path'] ) : null,
				'image_url'     => ! empty( $data['image_url'] ) ? esc_url_raw( $data['image_url'] ) : null,
				'status'        => sanitize_key( $data['status'] ?? 'pending' ),
				'error_message' => ! empty( $data['error_message'] ) ? sanitize_textarea_field( $data['error_message'] ) : null,
				'created_at'    => $now,
				'expires_at'    => $expires_at,
			],
			[
				'%s',
				'%s',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
			]
		);
	}

	public function get_results_by_job_id( string $job_id ): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->results_table} WHERE job_id = %s ORDER BY id ASC",
				sanitize_text_field( $job_id )
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : [];
	}

	private function get_expires_at(): string {
		$retention_minutes = absint(
			get_option( 'wpmt_result_retention_minutes', 30 )
		);

		if ( $retention_minutes < 5 ) {
			$retention_minutes = 30;
		}

		return current_datetime()
			->modify( '+' . $retention_minutes . ' minutes' )
			->format( 'Y-m-d H:i:s' );
	}

}