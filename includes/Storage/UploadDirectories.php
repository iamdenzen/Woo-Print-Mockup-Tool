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

	public static function ensure(): void {
		wp_mkdir_p( self::base_dir() . '/artwork' );
		wp_mkdir_p( self::base_dir() . '/results' );

		$index = self::base_dir() . '/index.html';
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, '' );
		}
	}
}
