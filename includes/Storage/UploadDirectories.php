<?php

namespace WooPrintMockupTool\Storage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class UploadDirectories {
	public static function base_dir(): string {
		$upload = wp_upload_dir();

		return trailingslashit( $upload['basedir'] ) . WPMT_UPLOAD_DIR_NAME;
	}

	public static function base_url(): string {
		$upload = wp_upload_dir();

		return trailingslashit( $upload['baseurl'] ) . WPMT_UPLOAD_DIR_NAME;
	}

	public static function artwork_dir(): string {
		return self::base_dir() . '/artwork';
	}

	public static function results_dir(): string {
		return self::base_dir() . '/results';
	}

	public static function job_results_dir( string $job_id ): string {
		return self::results_dir() . '/jobs/' . sanitize_file_name( $job_id );
	}

	public static function session_results_dir( string $session_key ): string {
		return self::results_dir() . '/sessions/' . sanitize_file_name( $session_key );
	}

	public static function ensure(): void {
		self::ensure_dir( self::base_dir() );
		self::ensure_dir( self::artwork_dir() );
		self::ensure_dir( self::results_dir() );
		self::ensure_dir( self::results_dir() . '/jobs' );
		self::ensure_dir( self::results_dir() . '/sessions' );
	}

	private static function ensure_dir( string $dir ): void {
		wp_mkdir_p( $dir );

		$index = trailingslashit( $dir ) . 'index.html';

		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, '' );
		}
	}
}