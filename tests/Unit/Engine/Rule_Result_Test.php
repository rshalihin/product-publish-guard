<?php
/**
 * Tests for the per-rule result value object.
 *
 * @package ProductPublishGuard
 */

namespace ProductPublishGuard\Tests\Unit\Engine;

use ProductPublishGuard\Engine\Rule_Result;
use ProductPublishGuard\Engine\Severity;
use ProductPublishGuard\Engine\Status;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Covers the factories, the severity matrix and the documented array shape.
 *
 * @since 1.0.0
 */
final class Rule_Result_Test extends TestCase {

	/**
	 * The four factories stamp the identity and the outcome onto the result.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_factories_set_identity_and_outcome() {
		$pass = Rule_Result::pass( 'sku', 'SKU', 'inventory' );
		$fail = Rule_Result::fail( 'sku', 'SKU', 'inventory', 'Add a SKU for this product.' );
		$warn = Rule_Result::warn( 'sku', 'SKU', 'inventory', 'Advisory.' );
		$skip = Rule_Result::skip( 'sku', 'SKU', 'inventory', 'Not applicable.' );

		$this->assertSame( 'sku', $pass->get_rule_id() );
		$this->assertSame( 'SKU', $pass->get_label() );
		$this->assertSame( 'inventory', $pass->get_group() );

		$this->assertSame( Status::PASS, $pass->get_outcome() );
		$this->assertSame( Status::FAIL, $fail->get_outcome() );
		$this->assertSame( Status::WARNING, $warn->get_outcome() );
		$this->assertSame( Status::SKIPPED, $skip->get_outcome() );

		$this->assertSame( 'Add a SKU for this product.', $fail->get_message() );
		$this->assertSame( Severity::REQUIRED, $pass->get_severity() );
	}

	/**
	 * A failure under `required` severity stays a failure.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_failure_under_required_severity_is_a_failure() {
		$result = Rule_Result::fail( 'price', 'Price', 'pricing', 'Set a regular price.' )
			->with_severity( Severity::REQUIRED );

		$this->assertSame( Status::FAIL, $result->get_status() );
		$this->assertSame( Severity::REQUIRED, $result->get_severity() );
	}

	/**
	 * A failure under `warning` severity is demoted to a warning.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_failure_under_warning_severity_is_demoted() {
		$result = Rule_Result::fail( 'tags', 'Tags', 'organization', 'Add at least one product tag.' )
			->with_severity( Severity::WARNING );

		$this->assertSame( Status::WARNING, $result->get_status() );
		$this->assertSame( Status::FAIL, $result->get_outcome(), 'The reported outcome is preserved.' );
	}

	/**
	 * A warning is advisory by nature and is never escalated.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_warning_is_never_escalated() {
		$result = Rule_Result::warn( 'stock_status', 'Stock status', 'inventory', 'Out of stock.' )
			->with_severity( Severity::REQUIRED );

		$this->assertSame( Status::WARNING, $result->get_status() );
	}

	/**
	 * A skipped rule stays skipped whatever the severity.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_skip_is_never_escalated() {
		$result = Rule_Result::skip( 'price', 'Price', 'pricing', 'Validated per variation.' )
			->with_severity( Severity::REQUIRED );

		$this->assertSame( Status::SKIPPED, $result->get_status() );
	}

	/**
	 * Applying a severity returns a copy and leaves the original alone.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_with_severity_does_not_mutate_the_original() {
		$original = Rule_Result::fail( 'tags', 'Tags', 'organization', 'Add a tag.' );
		$demoted  = $original->with_severity( Severity::WARNING );

		$this->assertNotSame( $original, $demoted );
		$this->assertSame( Status::FAIL, $original->get_status() );
		$this->assertSame( Status::WARNING, $demoted->get_status() );
	}

	/**
	 * The data payload carries numbers and booleans, and nothing else.
	 *
	 * This is the structural stored-XSS mitigation: product text cannot reach the UI
	 * because the value object refuses to carry it.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_data_payload_drops_everything_that_is_not_a_number_or_boolean() {
		$result = Rule_Result::fail(
			'image_count',
			'Product images',
			'media',
			'Too few images.',
			array(
				'image_count' => 1,
				'ratio'       => 0.5,
				'is_ready'    => false,
				'title'       => '<script>alert(1)</script>',
				'ids'         => array( 1, 2 ),
				'nothing'     => null,
			)
		);

		$this->assertSame(
			array(
				'image_count' => 1,
				'ratio'       => 0.5,
				'is_ready'    => false,
			),
			$result->get_data()
		);
	}

	/**
	 * A fix target without a selector is dropped; a complete one is normalized.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_fix_target_is_normalized() {
		$without = Rule_Result::fail( 'title', 'Title', 'content', 'Add a title.', array(), array( 'label' => 'Go' ) );
		$with    = Rule_Result::fail( 'sku', 'SKU', 'inventory', 'Add a SKU.', array(), array( 'selector' => '#_sku' ) );

		$this->assertSame( array(), $without->get_fix() );
		$this->assertSame(
			array(
				'selector' => '#_sku',
				'label'    => '',
				'panel'    => '',
			),
			$with->get_fix()
		);
	}

	/**
	 * `to_array()` matches coding-plan.md section 5.4 exactly, keys and order.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_to_array_matches_the_documented_shape() {
		$result = Rule_Result::fail(
			'featured_image',
			'Featured image',
			'media',
			'This product has no featured image.',
			array( 'image_count' => 0 ),
			array(
				'selector' => '#set-post-thumbnail',
				'label'    => 'Set featured image',
				'panel'    => '',
			)
		)->with_severity( Severity::REQUIRED );

		$this->assertSame(
			array(
				'rule_id'  => 'featured_image',
				'status'   => 'fail',
				'severity' => 'required',
				'label'    => 'Featured image',
				'message'  => 'This product has no featured image.',
				'group'    => 'media',
				'data'     => array( 'image_count' => 0 ),
				'fix'      => array(
					'selector' => '#set-post-thumbnail',
					'label'    => 'Set featured image',
					'panel'    => '',
				),
			),
			$result->to_array()
		);
	}

	/**
	 * An unknown severity falls back to `required` rather than being stored as-is.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_unknown_severity_falls_back_to_required() {
		$result = Rule_Result::fail( 'sku', 'SKU', 'inventory', 'Add a SKU.' )->with_severity( 'critical' );

		$this->assertSame( Severity::REQUIRED, $result->get_severity() );
		$this->assertSame( Status::FAIL, $result->get_status() );
	}
}
