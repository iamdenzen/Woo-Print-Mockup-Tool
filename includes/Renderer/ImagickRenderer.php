<?php

namespace WooPrintMockupTool\Renderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ImagickRenderer implements RendererInterface {

	public function render( array $args ): array {
		if ( ! class_exists( '\Imagick' ) ) {
			return $this->error(
				__( 'Imagick is not available on this server.', 'woo-print-mockup-tool' )
			);
		}

		$required = [
			'mockup_path',
			'artwork_path',
			'placement_data',
			'output_path',
		];

		foreach ( $required as $key ) {
			if ( empty( $args[ $key ] ) ) {
				return $this->error(
					sprintf(
						/* translators: %s: missing render argument */
						__( 'Missing render argument: %s', 'woo-print-mockup-tool' ),
						$key
					)
				);
			}
		}

		if ( ! is_readable( $args['mockup_path'] ) ) {
			return $this->error(
				__( 'Mockup image is not readable.', 'woo-print-mockup-tool' )
			);
		}

		if ( ! is_readable( $args['artwork_path'] ) ) {
			return $this->error(
				__( 'Artwork image is not readable.', 'woo-print-mockup-tool' )
			);
		}

		$placement = is_array( $args['placement_data'] )
			? $args['placement_data']
			: [];

		$placement_type = sanitize_key(
			$placement['type'] ?? 'rectangle'
		);

		try {
			$mockup  = new \Imagick( $args['mockup_path'] );
			$artwork = new \Imagick( $args['artwork_path'] );

			$this->prepare_image( $mockup );
			$this->prepare_image( $artwork );

			$this->remove_white_background( $artwork );

			if ( 'engraving' === sanitize_key( $args['render_mode'] ?? 'color' ) ) {
				$this->apply_engraving_effect( $artwork );
			}

			switch ( $placement_type ) {
				case 'perspective':
					$this->render_perspective(
						$mockup,
						$artwork,
						$placement
					);
					break;

				case 'rectangle':
					$this->render_rectangle(
						$mockup,
						$artwork,
						$placement
					);
					break;

				default:
					throw new \RuntimeException(
						__( 'Unsupported placement type.', 'woo-print-mockup-tool' )
					);
			}

			$this->write_output(
				$mockup,
				$args['output_path']
			);

			$artwork->clear();
			$artwork->destroy();

			$mockup->clear();
			$mockup->destroy();

			return [
				'success'     => true,
				'output_path' => $args['output_path'],
			];
		} catch ( \Throwable $e ) {
			if ( isset( $artwork ) && $artwork instanceof \Imagick ) {
				$artwork->clear();
				$artwork->destroy();
			}

			if ( isset( $mockup ) && $mockup instanceof \Imagick ) {
				$mockup->clear();
				$mockup->destroy();
			}

			return $this->error( $e->getMessage() );
		}
	}

	private function render_rectangle(
		\Imagick $mockup,
		\Imagick $artwork,
		array $placement
	): void {
		$mockup_width  = $mockup->getImageWidth();
		$mockup_height = $mockup->getImageHeight();

		$rectangle = $this->resolve_rectangle(
			$placement,
			$mockup_width,
			$mockup_height
		);

		$artwork->resizeImage(
			$rectangle['width'],
			$rectangle['height'],
			\Imagick::FILTER_LANCZOS,
			1,
			true
		);

		$canvas = new \Imagick();
		$canvas->newImage(
			$rectangle['width'],
			$rectangle['height'],
			new \ImagickPixel( 'transparent' ),
			'png'
		);
		$canvas->setImageAlphaChannel( \Imagick::ALPHACHANNEL_SET );

		$offset_x = (int) floor(
			( $rectangle['width'] - $artwork->getImageWidth() ) / 2
		);

		$offset_y = (int) floor(
			( $rectangle['height'] - $artwork->getImageHeight() ) / 2
		);

		$canvas->compositeImage(
			$artwork,
			\Imagick::COMPOSITE_OVER,
			$offset_x,
			$offset_y
		);

		$mockup->compositeImage(
			$canvas,
			\Imagick::COMPOSITE_OVER,
			$rectangle['x'],
			$rectangle['y']
		);

		$canvas->clear();
		$canvas->destroy();
	}

	private function render_perspective(
		\Imagick $mockup,
		\Imagick $artwork,
		array $placement
	): void {
		$mockup_width  = $mockup->getImageWidth();
		$mockup_height = $mockup->getImageHeight();

		$points = $this->resolve_perspective_points(
			$placement,
			$mockup_width,
			$mockup_height
		);

		$target_width = max(
			$this->distance( $points[0], $points[1] ),
			$this->distance( $points[3], $points[2] )
		);

		$target_height = max(
			$this->distance( $points[0], $points[3] ),
			$this->distance( $points[1], $points[2] )
		);

		$target_width  = max( 1, (int) round( $target_width ) );
		$target_height = max( 1, (int) round( $target_height ) );

		$artwork->resizeImage(
			$target_width,
			$target_height,
			\Imagick::FILTER_LANCZOS,
			1,
			true
		);

		$source_width  = $artwork->getImageWidth();
		$source_height = $artwork->getImageHeight();

		$source_right  = max( 0, $source_width - 1 );
		$source_bottom = max( 0, $source_height - 1 );

		/*
		 * Control-point order:
		 *
		 * Source top-left     -> destination top-left
		 * Source top-right    -> destination top-right
		 * Source bottom-right -> destination bottom-right
		 * Source bottom-left  -> destination bottom-left
		 */
		$distortion_arguments = [
			0,
			0,
			$points[0]['x'],
			$points[0]['y'],

			$source_right,
			0,
			$points[1]['x'],
			$points[1]['y'],

			$source_right,
			$source_bottom,
			$points[2]['x'],
			$points[2]['y'],

			0,
			$source_bottom,
			$points[3]['x'],
			$points[3]['y'],
		];

		$artwork->setImageVirtualPixelMethod(
			\Imagick::VIRTUALPIXELMETHOD_TRANSPARENT
		);

		/*
		 * Force the distorted image onto a canvas matching the complete
		 * mockup. This allows destination points to use absolute mockup
		 * coordinates and avoids ImageMagick page-offset complications.
		 */
		$artwork->setOption(
			'distort:viewport',
			sprintf(
				'%dx%d+0+0',
				$mockup_width,
				$mockup_height
			)
		);

		$artwork->distortImage(
			\Imagick::DISTORTION_PERSPECTIVE,
			$distortion_arguments,
			false
		);

		$artwork->setImagePage(
			$mockup_width,
			$mockup_height,
			0,
			0
		);

		$mockup->compositeImage(
			$artwork,
			\Imagick::COMPOSITE_OVER,
			0,
			0
		);
	}

	private function resolve_rectangle(
		array $placement,
		int $mockup_width,
		int $mockup_height
	): array {
		if (
			isset(
				$placement['left'],
				$placement['top'],
				$placement['width'],
				$placement['height']
			)
		) {
			$x = (int) round(
				$this->normalized_value( $placement['left'] ) * $mockup_width
			);

			$y = (int) round(
				$this->normalized_value( $placement['top'] ) * $mockup_height
			);

			$width = (int) round(
				$this->normalized_value( $placement['width'] ) * $mockup_width
			);

			$height = (int) round(
				$this->normalized_value( $placement['height'] ) * $mockup_height
			);
		} else {
			$x      = absint( $placement['x'] ?? 0 );
			$y      = absint( $placement['y'] ?? 0 );
			$width  = absint( $placement['width'] ?? 0 );
			$height = absint( $placement['height'] ?? 0 );
		}

		if ( $width <= 0 || $height <= 0 ) {
			throw new \InvalidArgumentException(
				__( 'Invalid rectangle placement dimensions.', 'woo-print-mockup-tool' )
			);
		}

		$x = max( 0, min( $x, $mockup_width - 1 ) );
		$y = max( 0, min( $y, $mockup_height - 1 ) );

		$width = min(
			$width,
			$mockup_width - $x
		);

		$height = min(
			$height,
			$mockup_height - $y
		);

		return [
			'x'      => $x,
			'y'      => $y,
			'width'  => max( 1, $width ),
			'height' => max( 1, $height ),
		];
	}

	private function resolve_perspective_points(
		array $placement,
		int $mockup_width,
		int $mockup_height
	): array {
		$points = $placement['points'] ?? [];

		if ( ! is_array( $points ) || 4 !== count( $points ) ) {
			throw new \InvalidArgumentException(
				__( 'Perspective placement requires exactly four points.', 'woo-print-mockup-tool' )
			);
		}

		$resolved = [];

		foreach ( $points as $point ) {
			if (
				! is_array( $point ) ||
				! isset( $point['x'], $point['y'] )
			) {
				throw new \InvalidArgumentException(
					__( 'Perspective placement contains an invalid point.', 'woo-print-mockup-tool' )
				);
			}

			$x = $this->normalized_value( $point['x'] );
			$y = $this->normalized_value( $point['y'] );

			$resolved[] = [
				'x' => $x * $mockup_width,
				'y' => $y * $mockup_height,
			];
		}

		if ( $this->polygon_area( $resolved ) < 4 ) {
			throw new \InvalidArgumentException(
				__( 'Perspective placement area is too small or invalid.', 'woo-print-mockup-tool' )
			);
		}

		return $resolved;
	}

	private function prepare_image( \Imagick $image ): void {
		$image->setIteratorIndex( 0 );
		$image->setImageColorspace( \Imagick::COLORSPACE_SRGB );
		$image->setImageAlphaChannel( \Imagick::ALPHACHANNEL_SET );
	}

	private function remove_white_background( \Imagick $image ): void {
		$image->setImageAlphaChannel( \Imagick::ALPHACHANNEL_SET );

		$iterator = $image->getPixelIterator();

		foreach ( $iterator as $row ) {
			foreach ( $row as $pixel ) {
				$color = $pixel->getColor();

				$red   = (int) ( $color['r'] ?? 0 );
				$green = (int) ( $color['g'] ?? 0 );
				$blue  = (int) ( $color['b'] ?? 0 );

				if ( $red >= 245 && $green >= 245 && $blue >= 245 ) {
					$pixel->setColor( 'rgba(255,255,255,0)' );
				}
			}

			$iterator->syncIterator();
		}
	}

	private function apply_engraving_effect( \Imagick $image ): void {
		$image->setImageType( \Imagick::IMGTYPE_GRAYSCALEMATTE );

		$image->colorizeImage(
			new \ImagickPixel( '#6f6f6f' ),
			0.65
		);

		$image->evaluateImage(
			\Imagick::EVALUATE_MULTIPLY,
			0.75,
			\Imagick::CHANNEL_ALPHA
		);
	}

	private function write_output(
		\Imagick $mockup,
		string $output_path
	): void {
		$output_dir = dirname( $output_path );

		if ( ! is_dir( $output_dir ) && ! wp_mkdir_p( $output_dir ) ) {
			throw new \RuntimeException(
				__( 'Could not create the render output directory.', 'woo-print-mockup-tool' )
			);
		}

		$mockup->setImageFormat( 'png' );
		$mockup->setImagePage( 0, 0, 0, 0 );

		if ( ! $mockup->writeImage( $output_path ) ) {
			throw new \RuntimeException(
				__( 'Could not write the rendered image.', 'woo-print-mockup-tool' )
			);
		}
	}

	private function normalized_value( mixed $value ): float {
		$value = (float) $value;

		return max( 0.0, min( 1.0, $value ) );
	}

	private function distance( array $first, array $second ): float {
		return sqrt(
			( ( $second['x'] - $first['x'] ) ** 2 ) +
			( ( $second['y'] - $first['y'] ) ** 2 )
		);
	}

	private function polygon_area( array $points ): float {
		$area = 0.0;
		$count = count( $points );

		for ( $index = 0; $index < $count; $index++ ) {
			$next = ( $index + 1 ) % $count;

			$area +=
				$points[ $index ]['x'] * $points[ $next ]['y'] -
				$points[ $next ]['x'] * $points[ $index ]['y'];
		}

		return abs( $area / 2 );
	}

	private function error( string $message ): array {
		return [
			'success' => false,
			'error'   => $message,
		];
	}
}