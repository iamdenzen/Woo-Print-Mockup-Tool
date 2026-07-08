<?php

namespace WooPrintMockupTool\Renderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RemoteRenderer implements RendererInterface {
	public function render( array $context ): array {
		// Future paid/external renderer service.
		return [
			'success' => false,
			'error'   => 'Remote renderer placeholder.',
		];
	}
}
