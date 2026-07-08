<?php

namespace WooPrintMockupTool\Services;

use WooPrintMockupTool\Storage\UploadDirectories;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ArtworkUploadService {
	private const ALLOWED_MIME_TYPES = [
		'image/png',
		'image/jpeg',
		'image/jpg',
	];

	public function handle_upload( array $file, string $context = 'api' ): array {
		if ( empty( $file['tmp_name'] ) || empty( $file['name'] ) ) {
			return [
				'success' => false,
				'error'   => __( 'No artwork file was uploaded.', 'woo-print-mockup-tool' ),
			];
		}

		if ( ! empty( $file['error'] ) ) {
			return [
				'success' => false,
				'error'   => __( 'Artwork upload failed.', 'woo-print-mockup-tool' ),
			];
		}

		$mime_type = $this->detect_mime_type( $file['tmp_name'] );

		if ( ! in_array( $mime_type, self::ALLOWED_MIME_TYPES, true ) ) {
			return [
				'success' => false,
				'error'   => __( 'Only PNG and JPG artwork files are supported in local rendering mode.', 'woo-print-mockup-tool' ),
			];
		}

		UploadDirectories::ensure();

		$extension = 'image/png' === $mime_type ? 'png' : 'jpg';
		$filename  = sanitize_file_name(
			$context . '-' . wp_generate_uuid4() . '.' . $extension
		);

		$target_path = trailingslashit( UploadDirectories::artwork_dir() ) . $filename;

		if ( ! move_uploaded_file( $file['tmp_name'], $target_path ) ) {
			return [
				'success' => false,
				'error'   => __( 'Could not save uploaded artwork file.', 'woo-print-mockup-tool' ),
			];
		}

		return [
			'success' => true,
			'path'    => $target_path,
			'url'     => trailingslashit( UploadDirectories::base_url() ) . 'artwork/' . $filename,
			'hash'    => hash_file( 'sha256', $target_path ),
			'mime'    => $mime_type,
		];
	}

	private function detect_mime_type( string $path ): string {
		if ( function_exists( 'wp_get_image_mime' ) ) {
			$mime = wp_get_image_mime( $path );

			if ( is_string( $mime ) && '' !== $mime ) {
				return $mime;
			}
		}

		if ( function_exists( 'mime_content_type' ) ) {
			$mime = mime_content_type( $path );

			if ( is_string( $mime ) && '' !== $mime ) {
				return $mime;
			}
		}

		return '';
	}
}