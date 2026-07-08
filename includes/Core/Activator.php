<?php

namespace WooPrintMockupTool\Core;

use WooPrintMockupTool\Database\Schema;
use WooPrintMockupTool\Storage\UploadDirectories;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Activator {
	public static function activate(): void {
		Schema::create_tables();
		UploadDirectories::ensure();

		if ( ! wp_next_scheduled( 'wpmt_cleanup_expired_mockups' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'wpmt_cleanup_expired_mockups' );
		}

		flush_rewrite_rules();
	}
}
