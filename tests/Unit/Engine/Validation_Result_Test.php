<?php
/**
 * Tests for the aggregate validation result.
 *
 * @package ProductPublishGuard
 */

namespace ProductPublishGuard\Tests\Unit\Engine;

use ProductPublishGuard\Engine\Product_Context;
use ProductPublishGuard\Engine\Rule_Result;
use ProductPublishGuard\Engine\Severity;
use ProductPublishGuard\Engine\Validation_Result;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Covers counting, readiness, required-failure collection and the array shape.
 *
 * @since 1.0.0
 */
final class Validation_Result_Test extends TestCase {

	/**
	 * A context for a saved simple product.
	 *
	 * @since 1.0.0
	 *
	 * @return Product_Context
	 */
	private function context(): Product_Context {
		return Product_Context::from_array(
			array(
				'product_id'   => 123,
				'product_type' => 'simple',
			)
		);
	}

	/**
	 * A mixed set of results: three passes, two warnings, one failure, two skips.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	private function mixed_results(): array {
		return array(
			Rule_Result::pass( 'title', 'Title', 'content' )->with_severity( Severity::REQUIRED ),
			Rule_Result::pass( 'description', 'Description', 'content' )->with_severity( Severity::REQUIRED ),
			Rule_Result::pass( 'category', 'Category', 'organization' )->with_severity( Severity::REQUIRED ),
			Rule_Result::warn( 'short_description', 'Short description', 'content', 'Short.' )->with_severity( Severity::WARNING ),
			Rule_Result::fail( 'tags', 'Tags', 'organization', 'Add a tag.' )->with_severity( Severity::WARNING ),
			Rule_Result::fail( 'featured_image', 'Featured image', 'media', 'No image.' )->with_severity( Severity::REQUIRED ),
			Rule_Result::skip( 'price', 'Price', 'pricing', 'Per variation.' )->with_severity( Severity::REQUIRED ),
			Rule_Result::skip( 'image_count', 'Images', 'media', 'Minimum is one.' )->with_severity( Severity::WARNING ),
		);
	}

	/**
	 * Skipped rules are excluded from the evaluated denominator.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_counts_exclude_skipped_from_evaluated() {
		$result = Validation_Result::from_results( $this->context(), $this->mixed_results(), 'hash' );

		$this->assertSame(
			array(
				'evaluated' => 6,
				'passed'    => 3,
				'warnings'  => 2,
				'failed'    => 1,
				'skipped'   => 2,
			),
			$result->get_counts()
		);
	}

	/**
	 * Readiness is false as soon as any result has the `fail` status.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_is_ready_is_false_when_a_failure_exists() {
		$failing = Validation_Result::from_results( $this->context(), $this->mixed_results(), 'hash' );

		$this->assertFalse( $failing->is_ready() );
	}

	/**
	 * A run with warnings but no failures is ready.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_is_ready_is_true_when_only_warnings_exist() {
		$results = array(
			Rule_Result::pass( 'title', 'Title', 'content' )->with_severity( Severity::REQUIRED ),
			Rule_Result::warn( 'sku', 'SKU', 'inventory', 'Add a SKU.' )->with_severity( Severity::WARNING ),
			Rule_Result::fail( 'tags', 'Tags', 'organization', 'Add a tag.' )->with_severity( Severity::WARNING ),
		);

		$result = Validation_Result::from_results( $this->context(), $results, 'hash' );

		$this->assertTrue( $result->is_ready() );
		$this->assertSame( array(), $result->get_required_failures() );
	}

	/**
	 * Only failures under `required` severity block publishing.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_required_failures_exclude_demoted_failures() {
		$result = Validation_Result::from_results( $this->context(), $this->mixed_results(), 'hash' );

		$this->assertSame( array( 'featured_image' ), $result->get_required_failures() );
	}

	/**
	 * An empty run is ready and reports zeroes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_an_empty_run_is_ready() {
		$result = Validation_Result::from_results( $this->context(), array(), 'hash' );

		$this->assertTrue( $result->is_ready() );
		$this->assertSame( 0, $result->get_counts()['evaluated'] );
		$this->assertSame( array(), $result->get_results() );
	}

	/**
	 * Anything that is not a rule result is discarded rather than counted.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_non_results_are_discarded() {
		$result = Validation_Result::from_results(
			$this->context(),
			array( 'nonsense', null, Rule_Result::pass( 'title', 'Title', 'content' ) ),
			'hash'
		);

		$this->assertCount( 1, $result->get_results() );
		$this->assertSame( 1, $result->get_counts()['passed'] );
	}

	/**
	 * The summary label reports passes over evaluated checks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_summary_label_reports_passes_over_evaluated() {
		$result = Validation_Result::from_results( $this->context(), $this->mixed_results(), 'hash' );

		$this->assertSame( '3 of 6 checks passed', $result->get_summary_label() );
	}

	/**
	 * `to_array()` matches coding-plan.md section 5.4 exactly, keys and order.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_to_array_matches_the_documented_shape() {
		$result = Validation_Result::from_results( $this->context(), $this->mixed_results(), 'a1b2' );
		$array  = $result->to_array();

		$this->assertSame(
			array(
				'product_id',
				'product_type',
				'results',
				'counts',
				'is_ready',
				'required_failure_ids',
				'summary_label',
				'generated_at',
				'settings_hash',
			),
			array_keys( $array )
		);

		$this->assertSame( 123, $array['product_id'] );
		$this->assertSame( 'simple', $array['product_type'] );
		$this->assertFalse( $array['is_ready'] );
		$this->assertSame( array( 'featured_image' ), $array['required_failure_ids'] );
		$this->assertSame( 'a1b2', $array['settings_hash'] );
		$this->assertIsInt( $array['generated_at'] );
		$this->assertCount( 8, $array['results'] );
		$this->assertSame( 'title', $array['results'][0]['rule_id'] );
	}
}
