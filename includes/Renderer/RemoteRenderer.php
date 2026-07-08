<?php

namespace WooPrintMockupTool\Renderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RemoteRenderer implements RendererInterface {
	public function render( array $args ): array {
		return [
			'success' => false,
			'error'   => __( 'Remote renderer is not implemented yet.', 'woo-print-mockup-tool' ),
		];
	}
}