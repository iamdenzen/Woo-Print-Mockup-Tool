<?php

namespace WooPrintMockupTool\Renderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RendererFactory {
	public static function make(): RendererInterface {
		$engine = get_option( 'wpmt_render_engine', 'imagick' );

		if ( 'remote' === $engine ) {
			return new RemoteRenderer();
		}

		return new ImagickRenderer();
	}
}
