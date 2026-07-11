<?php

namespace WooPrintMockupTool\Storage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CleanupService {
	public function register(): void {
		add_action( 'wpmt_cleanup_expired_mockups', [ $this, 'cleanup' ] );
	}

	public function cleanup(): void {
		global $wpdb;

		$now = current_time( 'mysql' );

		$results_table  = $wpdb->prefix . 'wpmt_render_results';
		$jobs_table     = $wpdb->prefix . 'wpmt_render_jobs';
		$sessions_table = $wpdb->prefix . 'wpmt_artwork_sessions';

		$expired_results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, image_path FROM {$results_table} WHERE expires_at <= %s",
				$now
			),
			ARRAY_A
		);

		foreach ( $expired_results as $result ) {
			if ( ! empty( $result['image_path'] ) ) {
				$this->delete_file_if_safe( $result['image_path'] );
			}
		}

		$expired_jobs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, artwork_path FROM {$jobs_table} WHERE expires_at <= %s",
				$now
			),
			ARRAY_A
		);

		foreach ( $expired_jobs as $job ) {
			if ( ! empty( $job['artwork_path'] ) ) {
				$this->delete_file_if_safe(
					$job['artwork_path']
				);
			}
		}

		$expired_sessions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, artwork_path FROM {$sessions_table} WHERE expires_at <= %s",
				$now
			),
			ARRAY_A
		);

		foreach ( $expired_sessions as $session ) {
			if ( ! empty( $session['artwork_path'] ) ) {
				$this->delete_file_if_safe( $session['artwork_path'] );
			}
		}

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$results_table} WHERE expires_at <= %s",
				$now
			)
		);

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$jobs_table} WHERE expires_at <= %s",
				$now
			)
		);

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$sessions_table} WHERE expires_at <= %s",
				$now
			)
		);

		$this->delete_empty_dirs( UploadDirectories::results_dir() );
	}

	private function delete_file_if_safe( string $path ): void {
		$path = wp_normalize_path( $path );
		$base = wp_normalize_path( UploadDirectories::base_dir() );

		if ( 0 !== strpos( $path, $base ) ) {
			return;
		}

		if ( is_file( $path ) && is_writable( $path ) ) {
			unlink( $path );
		}
	}

	private function delete_empty_dirs( string $dir ): void {
		if ( ! is_dir( $dir ) || ! is_readable( $dir ) ) {
			return;
		}

		$items = scandir( $dir );

		if ( ! is_array( $items ) ) {
			return;
		}

		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item || 'index.html' === $item ) {
				continue;
			}

			$path = trailingslashit( $dir ) . $item;

			if ( is_dir( $path ) ) {
				$this->delete_empty_dirs( $path );

				$remaining = array_diff( scandir( $path ) ?: [], [ '.', '..', 'index.html' ] );

				if ( empty( $remaining ) ) {
					@rmdir( $path );
				}
			}
		}
	}
}