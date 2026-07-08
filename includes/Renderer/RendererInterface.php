<?php

namespace WooPrintMockupTool\Renderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface RendererInterface {
	/**
	 * @param array $args {
	 *     @type int    $product_id
	 *     @type string $mockup_path
	 *     @type string $artwork_path
	 *     @type array  $placement_data
	 *     @type string $render_mode
	 *     @type string $output_path
	 * }
	 */
	public function render( array $args ): array;
}