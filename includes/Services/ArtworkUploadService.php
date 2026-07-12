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

	private const MAX_FILE_SIZE = 10 * MB_IN_BYTES;

	public function handle_upload(
		array $file,
		string $context = 'api'
	): array {
		$validation = $this->validate_upload( $file );

		if ( empty( $validation['success'] ) ) {
			return $validation;
		}

		return $this->store_file(
			$file['tmp_name'],
			$validation['mime'],
			$context,
			true
		);
	}

	public function handle_url(
		string $url,
		string $context = 'api'
	): array {
		$url = esc_url_raw( trim( $url ) );

		if ( '' === $url ) {
			return [
				'success' => false,
				'error'   => __(
					'Artwork URL is required.',
					'woo-print-mockup-tool'
				),
			];
		}

		if ( ! wp_http_validate_url( $url ) ) {
			return [
				'success' => false,
				'error'   => __(
					'Artwork URL is not a valid public HTTP or HTTPS URL.',
					'woo-print-mockup-tool'
				),
			];
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$temp_path = download_url(
			$url,
			30
		);

		if ( is_wp_error( $temp_path ) ) {
			return [
				'success' => false,
				'error'   => sprintf(
					/* translators: %s: download error */
					__(
						'Artwork could not be downloaded: %s',
						'woo-print-mockup-tool'
					),
					$temp_path->get_error_message()
				),
			];
		}

		try {
			if ( ! is_file( $temp_path ) ) {
				return [
					'success' => false,
					'error'   => __(
						'Downloaded artwork file could not be found.',
						'woo-print-mockup-tool'
					),
				];
			}

			$file_size = filesize( $temp_path );

			if (
				false === $file_size
				|| $file_size <= 0
			) {
				return [
					'success' => false,
					'error'   => __(
						'Downloaded artwork file is empty.',
						'woo-print-mockup-tool'
					),
				];
			}

			if ( $file_size > self::MAX_FILE_SIZE ) {
				return [
					'success' => false,
					'error'   => sprintf(
						/* translators: %d: maximum megabytes */
						__(
							'Artwork file must not exceed %d MB.',
							'woo-print-mockup-tool'
						),
						(int) ( self::MAX_FILE_SIZE / MB_IN_BYTES )
					),
				];
			}

			$mime_type = $this->detect_mime_type(
				$temp_path
			);

			if (
				! in_array(
					$mime_type,
					self::ALLOWED_MIME_TYPES,
					true
				)
			) {
				return [
					'success' => false,
					'error'   => __(
						'Only PNG and JPG artwork files are supported in local rendering mode.',
						'woo-print-mockup-tool'
					),
				];
			}

			$result = $this->store_file(
				$temp_path,
				$mime_type,
				$context,
				false
			);

			if ( ! empty( $result['success'] ) ) {
				$result['source_url'] = $url;
			}

			return $result;
		} finally {
			if (
				is_string( $temp_path )
				&& is_file( $temp_path )
			) {
				@unlink( $temp_path );
			}
		}
	}

	public function validate_upload(
		array $file
	): array {
		if (
			empty( $file['tmp_name'] )
			|| empty( $file['name'] )
		) {
			return [
				'success' => false,
				'error'   => __(
					'No artwork file was uploaded.',
					'woo-print-mockup-tool'
				),
			];
		}

		if ( ! empty( $file['error'] ) ) {
			return [
				'success' => false,
				'error'   => __(
					'Artwork upload failed.',
					'woo-print-mockup-tool'
				),
			];
		}

		if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
			return [
				'success' => false,
				'error'   => __(
					'Artwork upload could not be verified.',
					'woo-print-mockup-tool'
				),
			];
		}

		$file_size = filesize( $file['tmp_name'] );

		if (
			false === $file_size
			|| $file_size <= 0
		) {
			return [
				'success' => false,
				'error'   => __(
					'Artwork file is empty.',
					'woo-print-mockup-tool'
				),
			];
		}

		if ( $file_size > self::MAX_FILE_SIZE ) {
			return [
				'success' => false,
				'error'   => sprintf(
					/* translators: %d: maximum megabytes */
					__(
						'Artwork file must not exceed %d MB.',
						'woo-print-mockup-tool'
					),
					(int) (
						self::MAX_FILE_SIZE
						/ MB_IN_BYTES
					)
				),
			];
		}

		$mime_type = $this->detect_mime_type(
			$file['tmp_name']
		);

		if (
			! in_array(
				$mime_type,
				self::ALLOWED_MIME_TYPES,
				true
			)
		) {
			return [
				'success' => false,
				'error'   => __(
					'Only PNG and JPG artwork files are supported in local rendering mode.',
					'woo-print-mockup-tool'
				),
			];
		}

		return [
			'success' => true,
			'mime'    => $mime_type,
		];
	}

	private function store_file(
		string $source_path,
		string $mime_type,
		string $context,
		bool $uploaded_file
	): array {
		UploadDirectories::ensure();

		$extension = 'image/png' === $mime_type
			? 'png'
			: 'jpg';

		$filename = sanitize_file_name(
			sanitize_key( $context )
			. '-'
			. wp_generate_uuid4()
			. '.'
			. $extension
		);

		$target_path = trailingslashit(
			UploadDirectories::artwork_dir()
		) . $filename;

		$stored = $uploaded_file
			? move_uploaded_file(
				$source_path,
				$target_path
			)
			: rename(
				$source_path,
				$target_path
			);

		if ( ! $stored ) {
			return [
				'success' => false,
				'error'   => __(
					'Could not save artwork file.',
					'woo-print-mockup-tool'
				),
			];
		}

		return [
			'success' => true,
			'path'    => $target_path,
			'url'     => trailingslashit(
				UploadDirectories::base_url()
			)
				. 'artwork/'
				. rawurlencode( $filename ),
			'hash'    => hash_file(
				'sha256',
				$target_path
			),
			'mime'    => $mime_type,
		];
	}

	private function detect_mime_type(
		string $path
	): string {
		if ( function_exists( 'wp_get_image_mime' ) ) {
			$mime = wp_get_image_mime( $path );

			if (
				is_string( $mime )
				&& '' !== $mime
			) {
				return $mime;
			}
		}

		if ( function_exists( 'mime_content_type' ) ) {
			$mime = mime_content_type( $path );

			if (
				is_string( $mime )
				&& '' !== $mime
			) {
				return $mime;
			}
		}

		return '';
	}
}