<?php

namespace WooPrintMockupTool\Renderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ImagickRenderer implements RendererInterface {
	public function render( array $args ): array {
		if ( ! class_exists( '\Imagick' ) ) {
			return [
				'success' => false,
				'error'   => __( 'Imagick is not available on this server.', 'woo-print-mockup-tool' ),
			];
		}

		$required = [
			'mockup_path',
			'artwork_path',
			'placement_data',
			'output_path',
		];

		foreach ( $required as $key ) {
			if ( empty( $args[ $key ] ) ) {
				return [
					'success' => false,
					'error'   => sprintf(
						/* translators: %s: missing render argument */
						__( 'Missing render argument: %s', 'woo-print-mockup-tool' ),
						$key
					),
				];
			}
		}

		if ( ! is_readable( $args['mockup_path'] ) ) {
			return [
				'success' => false,
				'error'   => __( 'Mockup image is not readable.', 'woo-print-mockup-tool' ),
			];
		}

		if ( ! is_readable( $args['artwork_path'] ) ) {
			return [
				'success' => false,
				'error'   => __( 'Artwork image is not readable.', 'woo-print-mockup-tool' ),
			];
		}

		$placement = is_array( $args['placement_data'] ) ? $args['placement_data'] : [];

		if ( ( $placement['type'] ?? '' ) !== 'rectangle' ) {
			return [
				'success' => false,
				'error'   => __( 'Only rectangle placement is implemented in local renderer v1.', 'woo-print-mockup-tool' ),
			];
		}

		$mockup_probe = new \Imagick( $args['mockup_path'] );
		$mockup_width = $mockup_probe->getImageWidth();
		$mockup_height = $mockup_probe->getImageHeight();
		$mockup_probe->clear();
		$mockup_probe->destroy();

		if ( isset( $placement['left'], $placement['top'], $placement['width'], $placement['height'] ) ) {
			$x      = (int) round( (float) $placement['left'] * $mockup_width );
			$y      = (int) round( (float) $placement['top'] * $mockup_height );
			$width  = (int) round( (float) $placement['width'] * $mockup_width );
			$height = (int) round( (float) $placement['height'] * $mockup_height );
		} else {
			$x      = isset( $placement['x'] ) ? (int) $placement['x'] : 0;
			$y      = isset( $placement['y'] ) ? (int) $placement['y'] : 0;
			$width  = isset( $placement['width'] ) ? (int) $placement['width'] : 0;
			$height = isset( $placement['height'] ) ? (int) $placement['height'] : 0;
		}

		if ( $width <= 0 || $height <= 0 ) {
			return [
				'success' => false,
				'error'   => __( 'Invalid rectangle placement dimensions.', 'woo-print-mockup-tool' ),
			];
		}

		try {
			$mockup  = new \Imagick( $args['mockup_path'] );
			$artwork = new \Imagick( $args['artwork_path'] );

			$mockup->setImageColorspace( \Imagick::COLORSPACE_SRGB );
			$mockup->setImageAlphaChannel( \Imagick::ALPHACHANNEL_SET );

			$artwork->setImageColorspace( \Imagick::COLORSPACE_SRGB );
			$artwork->setImageAlphaChannel( \Imagick::ALPHACHANNEL_SET );

			$this->remove_white_background( $artwork );

			if ( 'engraving' === sanitize_key( $args['render_mode'] ?? 'color' ) ) {
				$this->apply_engraving_effect( $artwork );
			}

			$artwork->resizeImage(
				$width,
				$height,
				\Imagick::FILTER_LANCZOS,
				1,
				true
			);

			$canvas = new \Imagick();
			$canvas->newImage( $width, $height, new \ImagickPixel( 'transparent' ), 'png' );
			$canvas->setImageAlphaChannel( \Imagick::ALPHACHANNEL_SET );

			$offset_x = (int) floor( ( $width - $artwork->getImageWidth() ) / 2 );
			$offset_y = (int) floor( ( $height - $artwork->getImageHeight() ) / 2 );

			$canvas->compositeImage(
				$artwork,
				\Imagick::COMPOSITE_OVER,
				$offset_x,
				$offset_y
			);

			$mockup->compositeImage(
				$canvas,
				\Imagick::COMPOSITE_OVER,
				$x,
				$y
			);

			$mockup->setImageFormat( 'png' );

			$output_dir = dirname( $args['output_path'] );

			if ( ! is_dir( $output_dir ) ) {
				wp_mkdir_p( $output_dir );
			}

			$mockup->writeImage( $args['output_path'] );

			$canvas->clear();
			$canvas->destroy();

			$artwork->clear();
			$artwork->destroy();

			$mockup->clear();
			$mockup->destroy();

			return [
				'success'     => true,
				'output_path' => $args['output_path'],
			];
		} catch ( \Throwable $e ) {
			return [
				'success' => false,
				'error'   => $e->getMessage(),
			];
		}
	}

	private function remove_white_background( \Imagick $image ): void {
		$image->setImageAlphaChannel( \Imagick::ALPHACHANNEL_SET );

		$iterator = $image->getPixelIterator();

		foreach ( $iterator as $row ) {
			foreach ( $row as $pixel ) {
				$color = $pixel->getColor();

				$r = (int) ( $color['r'] ?? 0 );
				$g = (int) ( $color['g'] ?? 0 );
				$b = (int) ( $color['b'] ?? 0 );

				if ( $r >= 245 && $g >= 245 && $b >= 245 ) {
					$pixel->setColor( 'rgba(255,255,255,0)' );
				}
			}

			$iterator->syncIterator();
		}
	}

	private function apply_engraving_effect( \Imagick $image ): void {
		$image->setImageType( \Imagick::IMGTYPE_GRAYSCALEMATTE );
		$image->colorizeImage( new \ImagickPixel( '#6f6f6f' ), 0.65 );
		$image->evaluateImage( \Imagick::EVALUATE_MULTIPLY, 0.75, \Imagick::CHANNEL_ALPHA );
	}
}