<?php

namespace WooPrintMockupTool\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ArtworkSessionRepository {
	private string $sessions_table;
	private string $results_table;

	public function __construct() {
		global $wpdb;

		$this->sessions_table = $wpdb->prefix . 'wpmt_artwork_sessions';
		$this->results_table  = $wpdb->prefix . 'wpmt_render_results';
	}

	public function get_by_session_key(
		string $session_key
	): ?array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT *
				FROM {$this->sessions_table}
				WHERE session_key = %s
				AND expires_at > %s
				LIMIT 1",
				$session_key,
				current_time( 'mysql' )
			),
			ARRAY_A
		);

		return is_array( $row )
			? $row
			: null;
	}

	public function upsert(
		string $session_key,
		array $data
	): bool {
		global $wpdb;

		$existing = $this->get_any_by_session_key(
			$session_key
		);

		$payload = [
			'artwork_path' => sanitize_text_field(
				$data['artwork_path'] ?? ''
			),
			'artwork_hash' => sanitize_text_field(
				$data['artwork_hash'] ?? ''
			),
			'expires_at'   => $this->get_expires_at(),
		];

		if ( $existing ) {
			return false !== $wpdb->update(
				$this->sessions_table,
				$payload,
				[
					'session_key' => $session_key,
				],
				[
					'%s',
					'%s',
					'%s',
				],
				[
					'%s',
				]
			);
		}

		$payload['session_key'] = $session_key;
		$payload['created_at']  = current_time( 'mysql' );

		return false !== $wpdb->insert(
			$this->sessions_table,
			$payload,
			[
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
			]
		);
	}

    private function get_any_by_session_key(
        string $session_key
    ): ?array {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                FROM {$this->sessions_table}
                WHERE session_key = %s
                LIMIT 1",
                $session_key
            ),
            ARRAY_A
        );

        return is_array( $row )
            ? $row
            : null;
    }

	public function get_result(
		string $session_key,
		int $product_id
	): ?array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT *
				FROM {$this->results_table}
				WHERE session_key = %s
				AND product_id = %d
				AND status = 'success'
				AND expires_at > %s
				ORDER BY id DESC
				LIMIT 1",
				$session_key,
				$product_id,
				current_time( 'mysql' )
			),
			ARRAY_A
		);

		return is_array( $row )
			? $row
			: null;
	}

	public function add_result(
		string $session_key,
		int $product_id,
		string $image_path,
		string $image_url
	): bool {
		global $wpdb;

		return false !== $wpdb->insert(
			$this->results_table,
			[
				'job_id'        => null,
				'session_key'   => $session_key,
				'product_id'    => $product_id,
				'image_path'    => $image_path,
				'image_url'     => $image_url,
				'status'        => 'success',
				'error_message' => null,
				'created_at'    => current_time( 'mysql' ),
				'expires_at'    => $this->get_expires_at(),
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

	public function get_results(
		string $session_key
	): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT *
				FROM {$this->results_table}
				WHERE session_key = %s",
				$session_key
			),
			ARRAY_A
		);

		return is_array( $rows )
			? $rows
			: [];
	}

	public function delete_results(
		string $session_key
	): bool {
		global $wpdb;

		return false !== $wpdb->delete(
			$this->results_table,
			[
				'session_key' => $session_key,
			],
			[
				'%s',
			]
		);
	}

	public function delete_session(
		string $session_key
	): bool {
		global $wpdb;

		return false !== $wpdb->delete(
			$this->sessions_table,
			[
				'session_key' => $session_key,
			],
			[
				'%s',
			]
		);
	}

	private function get_expires_at(): string {
		$retention_minutes = absint(
			get_option(
				'wpmt_result_retention_minutes',
				30
			)
		);

		if ( $retention_minutes < 5 ) {
			$retention_minutes = 30;
		}

		return current_datetime()
			->modify(
				'+' . $retention_minutes . ' minutes'
			)
			->format( 'Y-m-d H:i:s' );
	}
}