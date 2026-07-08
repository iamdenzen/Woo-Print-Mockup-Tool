<?php

namespace WooPrintMockupTool\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AdminServiceProvider {
	public function register(): void {
		( new ProductMockupPanel() )->register();
		( new SettingsPage() )->register();
	}
}
