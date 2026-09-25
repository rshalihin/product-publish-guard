<?php
/**
 * Tests for the SKU rule.
 *
 * @package ProductPublishGuard
 */

namespace ProductPublishGuard\Tests\Unit\Rules;

use ProductPublishGuard\Engine\Product_Context;
use ProductPublishGuard\Engine\Rule_Result;
use ProductPublishGuard\Engine\Status;
use ProductPublishGuard\Rules\Sku_Rule;
use ProductPublishGuard\Settings\Settings;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Covers both branches of the SKU rule.
 *
 * @since 1.0.0
 */
final class Sku_Rule_Test extends TestCase {

	/**
	 * Run the rule.
	 *
	 * @since 1.0.0
	 *
	 * @param string $sku Product SKU.
	 * @return Rule_Result
	 */
	private function check( string $sku ): Rule_Result {
		return ( new Sku_Rule() )->check(
			Product_Context::from_array( array( 'sku' => $sku ) ),
			$this->createMock( Settings::class )
		);
	}

	/**
	 * No SKU reports an unmet requirement.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_an_empty_sku_fails() {
		$result = $this->check( '' );

		$this->assertSame( Status::FAIL, $result->get_status() );
		$this->assertSame( 'Add a SKU for this product.', $result->get_message() );
	}

	/**
	 * Whitespace is not a SKU.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_a_whitespace_only_sku_fails() {
		$this->assertSame( Status::FAIL, $this->check( "  \t " )->get_status() );
	}

	/**
	 * Any real SKU passes; uniqueness is WooCommerce's job, not this rule's.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_a_set_sku_passes() {
		$this->assertSame( Status::PASS, $this->check( 'MUG-001' )->get_status() );
		$this->assertSame( Status::PASS, $this->check( '0' )->get_status() );
	}

	/**
	 * The rule points the merchant at the inventory panel.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_the_fix_target_is_the_inventory_panel() {
		$fix = $this->check( '' )->get_fix();

		$this->assertSame( '#_sku', $fix['selector'] );
		$this->assertSame( 'inventory', $fix['panel'] );
	}
}
