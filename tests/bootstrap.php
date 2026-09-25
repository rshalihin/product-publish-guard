<?php
/**
 * PHPUnit bootstrap.
 *
 * Two modes. When a WordPress test suite is available (the `WP_TESTS_DIR` or
 * `WP_PHPUNIT__DIR` environment variable, which `wp-env` sets), the plugin is loaded
 * into a real WordPress install for the integration and security suites. Otherwise the
 * unit suite runs against test doubles, with no WordPress at all — which is possible
 * only because the rule engine reads its data through `Product_Context`.
 *
 * @package ProductPublishGuard
 */

$wcpg_root = dirname( __DIR__ );

require_once $wcpg_root . '/vendor/autoload.php';

$wcpg_wp_tests = getenv( 'WP_TESTS_DIR' );

if ( ! $wcpg_wp_tests ) {
	$wcpg_wp_tests = getenv( 'WP_PHPUNIT__DIR' );
}

if ( $wcpg_wp_tests && file_exists( $wcpg_wp_tests . '/includes/functions.php' ) ) {
	require_once $wcpg_wp_tests . '/includes/functions.php';

	tests_add_filter(
		'muplugins_loaded',
		static function () use ( $wcpg_root ) {
			require $wcpg_root . '/product-publish-guard.php';
		}
	);

	require $wcpg_wp_tests . '/includes/bootstrap.php';

	/*
	 * After the suite has booted, the plugin's autoloader is registered, so the rule
	 * double can be declared. The integration tests use it to build a registry whose
	 * rules are known, rather than asserting against the eleven shipped ones.
	 */
	require_once __DIR__ . '/stubs/class-fake-rule.php';

	return;
}

/*
 * ABSPATH is WordPress's own constant, and the unit suite has to stand in for it because
 * every plugin file guards on it.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
defined( 'ABSPATH' ) || define( 'ABSPATH', $wcpg_root . '/' );
defined( 'WCPG_PATH' ) || define( 'WCPG_PATH', $wcpg_root . '/' );
defined( 'WCPG_VERSION' ) || define( 'WCPG_VERSION', '1.0.0' );

require_once __DIR__ . '/stubs/wordpress-functions.php';

if ( ! class_exists( 'WC_Product' ) ) {
	require_once __DIR__ . '/stubs/class-wc-product.php';
}

require_once $wcpg_root . '/src/Autoloader.php';

ProductPublishGuard\Autoloader::register();

require_once __DIR__ . '/stubs/class-fake-rule.php';
