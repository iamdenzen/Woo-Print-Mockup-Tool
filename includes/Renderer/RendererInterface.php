<?php

namespace WooPrintMockupTool\Renderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface RendererInterface {
	public function render( array $context ): array;
}
