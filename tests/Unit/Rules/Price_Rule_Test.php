<?php
/**
 * Tests for the regular price rule.
 *
 * @package ProductPublishGuard
 */

namespace ProductPublishGuard\Tests\Unit\Rules;

use ProductPublishGuard\Engine\Product_Context;
use ProductPublishGuard\Engine\Rule_Result;
use ProductPublishGuard\Engine\Status;
use ProductPublishGuard\Rules\Price_Rule;
use ProductPublishGuard\Settings\Settings;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Covers the missing, malformed, free and skipped branches.
 *
 * @since 1.0.0
 */
final class Price_Rule_Test extends TestCase {

	/**
	 * Run the rule.
	 *
	 * @since 1.0.0
	 *
	 * @param string $price Regular price, as stored.
	 * @param string $type  WooCommerce product type.
	 * @return Rule_Result
	 */
	private function check( string $price, string $type = 'simple' ): Rule_Result {
		return ( new Price_Rule() )->check(
			Product_Context::from_array(
				array(
					'regular_price' => $price,
					'product_type'  => $type,
				)
			),
			$this->createMock( Settings::class )
		);
	}

	/**
	 * No price at all is an unmet requirement.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_an_empty_price_fails() {
		$result = $this->check( '' );

		$this->assertSame( Status::FAIL, $result->get_status() );
		$this->assertSame( 'Set a regular price.', $result->get_message() );
	}

	/**
	 * Whitespace is not a price.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_a_whitespace_price_fails_as_empty() {
		$this->assertSame( 'Set a regular price.', $this->check( '   ' )->get_message() );
	}

	/**
	 * Something that is not a number gets its own message.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_a_non_numeric_price_fails() {
		$result = $this->check( 'nineteen' );

		$this->assertSame( Status::FAIL, $result->get_status() );
		$this->assertSame( 'The regular price is not a valid number.', $result->get_message() );
	}

	/**
	 * A negative price is not a price.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_a_negative_price_fails() {
		$result = $this->check( '-5' );

		$this->assertSame( Status::FAIL, $result->get_status() );
		$this->assertSame( 'The regular price is not a valid number.', $result->get_message() );
	}

	/**
	 * Free products are legitimate, so zero passes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_a_price_of_zero_passes() {
		$this->assertSame( Status::PASS, $this->check( '0' )->get_status() );
		$this->assertSame( Status::PASS, $this->check( '0.00' )->get_status() );
	}

	/**
	 * An ordinary price passes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_an_ordinary_price_passes() {
		$this->assertSame( Status::PASS, $this->check( '19.99' )->get_status() );
	}

	/**
	 * A variable product's price lives on its variations, which V1 does not read.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_a_variable_product_is_skipped() {
		$result = $this->check( '', 'variable' );

		$this->assertSame( Status::SKIPPED, $result->get_status() );
		$this->assertStringContainsString( 'per variation', $result->get_message() );
	}

	/**
	 * A grouped product takes its price from its children.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_a_grouped_product_is_skipped() {
		$result = $this->check( '', 'grouped' );

		$this->assertSame( Status::SKIPPED, $result->get_status() );
		$this->assertStringContainsString( 'grouped product', $result->get_message() );
	}

	/**
	 * An external product does carry its own price, so it is checked like a simple one.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_an_external_product_is_still_checked() {
		$this->assertSame( Status::FAIL, $this->check( '', 'external' )->get_status() );
	}
}
