<?php
/**
 * Plugin Name: Woo Print Mockup Tool
 * Description: Create print/mockup previews by placing customer artwork onto configured WooCommerce product images.
 * Version: 0.1.0
 * Author: Creatricx
 * Text Domain: woo-print-mockup-tool
 * Domain Path: /languages
 * Requires PHP: 8.0
 * Requires at least: 6.0
 * WC requires at least: 7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPMT_VERSION', '0.1.0' );
define( 'WPMT_PLUGIN_FILE', __FILE__ );
define( 'WPMT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPMT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPMT_UPLOAD_DIR_NAME', 'woo-print-mockup-tool' );

require_once WPMT_PLUGIN_DIR . 'includes/Core/Autoloader.php';

\WooPrintMockupTool\Core\Autoloader::register();

register_activation_hook( __FILE__, [ \WooPrintMockupTool\Core\Activator::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ \WooPrintMockupTool\Core\Deactivator::class, 'deactivate' ] );

add_action( 'plugins_loaded', static function () {
	\WooPrintMockupTool\Plugin::instance()->boot();
} );
