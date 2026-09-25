<?php
/**
 * Tests for the rule registry.
 *
 * @package ProductPublishGuard
 */

namespace ProductPublishGuard\Tests\Unit\Engine;

use ProductPublishGuard\Engine\Product_Context;
use ProductPublishGuard\Engine\Rule_Registry;
use ProductPublishGuard\Settings\Settings;
use ProductPublishGuard\Tests\Stubs\Fake_Rule;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Covers duplicate rejection, ordering and active-rule filtering.
 *
 * @since 1.0.0
 */
final class Rule_Registry_Test extends TestCase {

	/**
	 * Map rules to their identifiers.
	 *
	 * @since 1.0.0
	 *
	 * @param array $rules Rule objects.
	 * @return array
	 */
	private function ids( array $rules ): array {
		return array_map(
			static function ( $rule ) {
				return $rule->get_id();
			},
			$rules
		);
	}

	/**
	 * A settings double that enables only the named rules.
	 *
	 * @since 1.0.0
	 *
	 * @param array $enabled Rule identifiers that are enabled.
	 * @return Settings
	 */
	private function settings_enabling( array $enabled ): Settings {
		$settings = $this->createMock( Settings::class );

		$settings->method( 'rule_is_enabled' )->willReturnCallback(
			static function ( $rule_id ) use ( $enabled ) {
				return in_array( $rule_id, $enabled, true );
			}
		);

		return $settings;
	}

	/**
	 * A registered rule can be found by identifier.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_register_stores_a_rule() {
		$registry = new Rule_Registry();
		$rule     = new Fake_Rule( 'title' );

		$this->assertTrue( $registry->register( $rule ) );
		$this->assertTrue( $registry->has( 'title' ) );
		$this->assertSame( $rule, $registry->get( 'title' ) );
		$this->assertSame( 1, $registry->count() );
	}

	/**
	 * An unknown identifier returns null rather than throwing.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_get_returns_null_for_an_unknown_id() {
		$registry = new Rule_Registry();

		$this->assertFalse( $registry->has( 'nope' ) );
		$this->assertNull( $registry->get( 'nope' ) );
	}

	/**
	 * A duplicate identifier is rejected and the first registration is kept.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_duplicate_ids_are_rejected() {
		$registry = new Rule_Registry();
		$first    = new Fake_Rule( 'title' );
		$second   = new Fake_Rule( 'title', 'fail' );

		$registry->register( $first );

		$this->assertFalse( $registry->register( $second ) );
		$this->assertSame( $first, $registry->get( 'title' ) );
		$this->assertSame( 1, $registry->count() );
	}

	/**
	 * A rule with an empty identifier is rejected.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_an_empty_id_is_rejected() {
		$registry = new Rule_Registry();

		$this->assertFalse( $registry->register( new Fake_Rule( '' ) ) );
		$this->assertSame( 0, $registry->count() );
	}

	/**
	 * Rules come back ordered by priority, then alphabetically by identifier.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_all_sorts_by_priority_then_id() {
		$registry = new Rule_Registry();

		$registry->register( new Fake_Rule( 'zulu', 'pass', 10 ) );
		$registry->register( new Fake_Rule( 'bravo', 'pass', 20 ) );
		$registry->register( new Fake_Rule( 'alpha', 'pass', 20 ) );
		$registry->register( new Fake_Rule( 'charlie', 'pass', 5 ) );

		$this->assertSame( array( 'charlie', 'zulu', 'alpha', 'bravo' ), $this->ids( $registry->all() ) );
	}

	/**
	 * The sorted list is rebuilt after a later registration.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_ordering_is_recomputed_after_a_late_registration() {
		$registry = new Rule_Registry();

		$registry->register( new Fake_Rule( 'bravo', 'pass', 20 ) );
		$this->assertSame( array( 'bravo' ), $this->ids( $registry->all() ) );

		$registry->register( new Fake_Rule( 'alpha', 'pass', 10 ) );
		$this->assertSame( array( 'alpha', 'bravo' ), $this->ids( $registry->all() ) );
	}

	/**
	 * Disabled rules and rules that do not support the product are filtered out.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_get_active_filters_disabled_and_unsupported_rules() {
		$registry = new Rule_Registry();

		$registry->register( new Fake_Rule( 'enabled_supported', 'pass', 10 ) );
		$registry->register( new Fake_Rule( 'disabled_supported', 'pass', 20 ) );
		$registry->register( new Fake_Rule( 'enabled_unsupported', 'pass', 30, false ) );

		$active = $registry->get_active(
			$this->settings_enabling( array( 'enabled_supported', 'enabled_unsupported' ) ),
			Product_Context::from_array( array( 'product_type' => 'variable' ) )
		);

		$this->assertSame( array( 'enabled_supported' ), $this->ids( $active ) );
	}

	/**
	 * Active rules keep the registry's ordering.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_get_active_preserves_ordering() {
		$registry = new Rule_Registry();

		$registry->register( new Fake_Rule( 'late', 'pass', 90 ) );
		$registry->register( new Fake_Rule( 'early', 'pass', 10 ) );

		$active = $registry->get_active(
			$this->settings_enabling( array( 'late', 'early' ) ),
			Product_Context::from_array( array() )
		);

		$this->assertSame( array( 'early', 'late' ), $this->ids( $active ) );
	}
}
