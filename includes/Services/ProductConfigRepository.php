<?php

namespace WooPrintMockupTool\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ProductConfigRepository {
	private string $table;

	public function __construct() {
		global $wpdb;

		$this->table = $wpdb->prefix . 'wpmt_product_configs';
	}

	public function get_by_product_id( int $product_id ): ?array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE product_id = %d LIMIT 1",
				$product_id
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return null;
		}

		$row['placement_data'] = $this->decode_placement_data( $row['placement_data'] ?? '' );

		return $row;
	}

	public function upsert( int $product_id, array $data ): bool {
		global $wpdb;

		$now = current_time( 'mysql' );

		$payload = [
			'product_id'       => $product_id,
			'enabled'          => ! empty( $data['enabled'] ) ? 1 : 0,
			'mockup_image_id'  => ! empty( $data['mockup_image_id'] ) ? absint( $data['mockup_image_id'] ) : null,
			'placement_type'   => $this->sanitize_placement_type( $data['placement_type'] ?? 'rectangle' ),
			'placement_data'   => $this->encode_placement_data( $data['placement_data'] ?? [] ),
			'render_mode'      => $this->sanitize_render_mode( $data['render_mode'] ?? 'color' ),
			'updated_at'       => $now,
		];

		$existing = $this->get_by_product_id( $product_id );

		if ( $existing ) {
			return false !== $wpdb->update(
				$this->table,
				$payload,
				[ 'product_id' => $product_id ],
				[
					'%d',
					'%d',
					'%d',
					'%s',
					'%s',
					'%s',
					'%s',
				],
				[ '%d' ]
			);
		}

		$payload['created_at'] = $now;

		return false !== $wpdb->insert(
			$this->table,
			$payload,
			[
				'%d',
				'%d',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
			]
		);
	}

	public function delete_by_product_id( int $product_id ): bool {
		global $wpdb;

		return false !== $wpdb->delete(
			$this->table,
			[ 'product_id' => $product_id ],
			[ '%d' ]
		);
	}

	private function sanitize_placement_type( string $placement_type ): string {
		$placement_type = sanitize_key( $placement_type );

		$allowed = [
			'rectangle',
			'perspective',
		];

		return in_array( $placement_type, $allowed, true ) ? $placement_type : 'rectangle';
	}

	private function sanitize_render_mode( string $render_mode ): string {
		$render_mode = sanitize_key( $render_mode );

		$allowed = [
			'color',
			'engraving',
		];

		return in_array( $render_mode, $allowed, true ) ? $render_mode : 'color';
	}

	private function encode_placement_data( array|string $placement_data ): string {
		if ( is_string( $placement_data ) ) {
			$decoded = json_decode( wp_unslash( $placement_data ), true );
			$placement_data = is_array( $decoded ) ? $decoded : [];
		}

		return wp_json_encode( $placement_data );
	}

	private function decode_placement_data( ?string $placement_data ): array {
		if ( empty( $placement_data ) ) {
			return [];
		}

		$decoded = json_decode( $placement_data, true );

		return is_array( $decoded ) ? $decoded : [];
	}
}