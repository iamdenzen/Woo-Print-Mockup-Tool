<?php

namespace WooPrintMockupTool\Storage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CleanupService {
	public function register(): void {
		add_action( 'wpmt_cleanup_expired_mockups', [ $this, 'cleanup' ] );
	}

	public function cleanup(): void {
		// Delete expired generated files and rows in implementation step.
	}
}
