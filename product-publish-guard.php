<?php
/**
 * Plugin Name:          Product Publish Guard
 * Plugin URI:           https://example.com/product-publish-guard
 * Description:          A pre-publish quality checklist for WooCommerce products, with optional server-side publishing enforcement.
 * Version:              1.0.0
 * Requires at least:    6.5
 * Requires PHP:         8.0
 * Requires Plugins:     woocommerce
 * Author:               Product Publish Guard Team
 * Author URI:           https://example.com
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          product-publish-guard
 * Domain Path:          /languages
 * WC requires at least: 9.0
 * WC tested up to:      11.1
 *
 * @package ProductPublishGuard
 */

defined( 'ABSPATH' ) || exit;

define( 'WCPG_VERSION', '1.0.0' );
define( 'WCPG_FILE', __FILE__ );
define( 'WCPG_PATH', plugin_dir_path( __FILE__ ) );
define( 'WCPG_URL', plugin_dir_url( __FILE__ ) );
define( 'WCPG_MIN_PHP', '8.0' );
define( 'WCPG_MIN_WP', '6.5' );
define( 'WCPG_MIN_WC', '9.0' );

require_once WCPG_PATH . 'src/Autoloader.php';

/**
 * Bootstrap the plugin.
 *
 * The only global function in the plugin. It registers the autoloader and the two
 * hooks that decide whether anything else runs at all:
 *
 * - `before_woocommerce_init` (fired by WooCommerce on `plugins_loaded`, priority -1)
 *   is the only window in which feature compatibility may be declared.
 * - `plugins_loaded` priority 5 is late enough that `WC_VERSION` is defined and early
 *   enough to register every other hook the plugin needs.
 *
 * @since 1.0.0
 *
 * @return void
 */
function wcpg_bootstrap() {
	\ProductPublishGuard\Autoloader::register();

	add_action(
		'before_woocommerce_init',
		array( \ProductPublishGuard\Compat\Woo_Compat::class, 'declare_compatibility' )
	);

	add_action( 'plugins_loaded', 'wcpg_boot_plugin', 5 );
}

/**
 * Check requirements and, if they are met, boot the plugin.
 *
 * Kept separate from `wcpg_bootstrap()` so the `plugins_loaded` callback is a named
 * function rather than a closure, which makes it unhookable-by-accident and testable.
 *
 * @since 1.0.0
 *
 * @return void
 */
function wcpg_boot_plugin() {
	if ( ! \ProductPublishGuard\Compat\Requirements::check() ) {
		return;
	}

	\ProductPublishGuard\Plugin::instance()->boot();
}

wcpg_bootstrap();
