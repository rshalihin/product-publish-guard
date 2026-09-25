<?php
/**
 * Tests for the image count rule.
 *
 * @package ProductPublishGuard
 */

namespace ProductPublishGuard\Tests\Unit\Rules;

use ProductPublishGuard\Engine\Product_Context;
use ProductPublishGuard\Engine\Rule_Result;
use ProductPublishGuard\Engine\Status;
use ProductPublishGuard\Rules\Image_Count_Rule;
use ProductPublishGuard\Settings\Settings;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Covers the threshold branches and the de-duplication the count relies on.
 *
 * @since 1.0.0
 */
final class Image_Count_Rule_Test extends TestCase {

	/**
	 * A settings double that answers the image threshold.
	 *
	 * @since 1.0.0
	 *
	 * @param int $minimum Value returned for `min_images`.
	 * @return Settings
	 */
	private function settings( int $minimum ): Settings {
		$settings = $this->createMock( Settings::class );

		$settings->method( 'threshold' )->willReturnCallback(
			static function ( $key ) use ( $minimum ) {
				return 'min_images' === $key ? $minimum : 0;
			}
		);

		return $settings;
	}

	/**
	 * Run the rule.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data    Context values.
	 * @param int   $minimum Configured threshold.
	 * @return Rule_Result
	 */
	private function check( array $data, int $minimum ): Rule_Result {
		return ( new Image_Count_Rule() )->check( Product_Context::from_array( $data ), $this->settings( $minimum ) );
	}

	/**
	 * Fewer images than the merchant asked for reports an unmet requirement.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_below_the_minimum_fails() {
		$result = $this->check(
			array(
				'featured_image_id' => 10,
				'gallery_image_ids' => array( 11 ),
			),
			3
		);

		$this->assertSame( Status::FAIL, $result->get_status() );
		$this->assertStringContainsString( '2', $result->get_message() );
		$this->assertStringContainsString( '3', $result->get_message() );
		$this->assertSame(
			array(
				'image_count' => 2,
				'minimum'     => 3,
			),
			$result->get_data()
		);
	}

	/**
	 * The threshold is inclusive.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_exactly_the_minimum_passes() {
		$result = $this->check(
			array(
				'featured_image_id' => 10,
				'gallery_image_ids' => array( 11 ),
			),
			2
		);

		$this->assertSame( Status::PASS, $result->get_status() );
	}

	/**
	 * A featured image that is also in the gallery is one image, not two.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_the_featured_image_is_counted_once() {
		$result = $this->check(
			array(
				'featured_image_id' => 10,
				'gallery_image_ids' => array( 10, 11 ),
			),
			2
		);

		$this->assertSame( 2, $result->get_data()['image_count'] );
		$this->assertSame( Status::PASS, $result->get_status() );
	}

	/**
	 * A minimum of one or less would only repeat the featured image rule, so the row
	 * takes itself out of the checklist instead.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_a_minimum_of_one_or_less_skips() {
		$this->assertSame( Status::SKIPPED, $this->check( array(), 1 )->get_status() );
		$this->assertSame( Status::SKIPPED, $this->check( array(), 0 )->get_status() );
		$this->assertSame(
			Status::SKIPPED,
			$this->check( array( 'featured_image_id' => 10 ), 1 )->get_status(),
			'A skip must not depend on the product having no images.'
		);
	}

	/**
	 * A product with no images at all fails rather than dividing by nothing.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_no_images_at_all_fails() {
		$result = $this->check( array(), 2 );

		$this->assertSame( Status::FAIL, $result->get_status() );
		$this->assertSame( 0, $result->get_data()['image_count'] );
	}
}
