<?php
/**
 * Tests for the stock status rule.
 *
 * @package ProductPublishGuard
 */

namespace ProductPublishGuard\Tests\Unit\Rules;

use ProductPublishGuard\Engine\Product_Context;
use ProductPublishGuard\Engine\Rule_Result;
use ProductPublishGuard\Engine\Status;
use ProductPublishGuard\Rules\Stock_Status_Rule;
use ProductPublishGuard\Settings\Settings;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Covers the missing, invalid, out-of-stock, contradictory and healthy branches.
 *
 * @since 1.0.0
 */
final class Stock_Status_Rule_Test extends TestCase {

	/**
	 * Run the rule.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Context values.
	 * @return Rule_Result
	 */
	private function check( array $data ): Rule_Result {
		return ( new Stock_Status_Rule() )->check(
			Product_Context::from_array( $data ),
			$this->createMock( Settings::class )
		);
	}

	/**
	 * No stock status reports an unmet requirement.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_an_empty_status_fails() {
		$result = $this->check( array( 'stock_status' => '' ) );

		$this->assertSame( Status::FAIL, $result->get_status() );
		$this->assertSame( 'Select a stock status.', $result->get_message() );
	}

	/**
	 * A value WooCommerce does not recognise fails the same way.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_an_unknown_status_fails() {
		$result = $this->check( array( 'stock_status' => 'maybe' ) );

		$this->assertSame( Status::FAIL, $result->get_status() );
		$this->assertSame( 'Select a stock status.', $result->get_message() );
	}

	/**
	 * Publishing something nobody can buy is worth mentioning, but it is the merchant's
	 * decision, so it is advisory.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_out_of_stock_warns() {
		$result = $this->check( array( 'stock_status' => 'outofstock' ) );

		$this->assertSame( Status::WARNING, $result->get_status() );
		$this->assertSame( 'This product is marked out of stock.', $result->get_message() );
	}

	/**
	 * In stock with nothing left is a contradiction that will take orders it cannot
	 * fulfil.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_managed_stock_at_zero_while_in_stock_warns() {
		$result = $this->check(
			array(
				'stock_status'   => 'instock',
				'manage_stock'   => true,
				'stock_quantity' => 0,
			)
		);

		$this->assertSame( Status::WARNING, $result->get_status() );
		$this->assertStringContainsString( '0', $result->get_message() );
		$this->assertSame( array( 'stock_quantity' => 0 ), $result->get_data() );
	}

	/**
	 * A negative managed quantity is the same contradiction.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_a_negative_managed_quantity_warns() {
		$result = $this->check(
			array(
				'stock_status'   => 'instock',
				'manage_stock'   => true,
				'stock_quantity' => -3,
			)
		);

		$this->assertSame( Status::WARNING, $result->get_status() );
		$this->assertSame( array( 'stock_quantity' => -3 ), $result->get_data() );
	}

	/**
	 * Without stock management there is no quantity to contradict the status.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_an_unmanaged_product_is_not_second_guessed() {
		$result = $this->check(
			array(
				'stock_status'   => 'instock',
				'manage_stock'   => false,
				'stock_quantity' => 0,
			)
		);

		$this->assertSame( Status::PASS, $result->get_status() );
	}

	/**
	 * A healthy product passes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_in_stock_passes() {
		$this->assertSame( Status::PASS, $this->check( array( 'stock_status' => 'instock' ) )->get_status() );
		$this->assertSame(
			Status::PASS,
			$this->check(
				array(
					'stock_status'   => 'instock',
					'manage_stock'   => true,
					'stock_quantity' => 12,
				)
			)->get_status()
		);
	}

	/**
	 * Backorders are a deliberate arrangement, not a mistake.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_on_backorder_passes() {
		$this->assertSame( Status::PASS, $this->check( array( 'stock_status' => 'onbackorder' ) )->get_status() );
	}
}
