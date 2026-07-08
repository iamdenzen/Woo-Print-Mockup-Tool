<?php

namespace WooPrintMockupTool\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Deactivator {
	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'wpmt_cleanup_expired_mockups' );
	}
}
