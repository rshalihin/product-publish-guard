<?php
/**
 * A configurable rule double for the engine tests.
 *
 * @package ProductPublishGuard
 */

namespace ProductPublishGuard\Tests\Stubs;

use ProductPublishGuard\Engine\Abstract_Rule;
use ProductPublishGuard\Engine\Product_Context;
use ProductPublishGuard\Engine\Rule_Result;
use ProductPublishGuard\Engine\Severity;
use ProductPublishGuard\Settings\Settings;
use RuntimeException;

/**
 * A rule whose outcome, priority, applicability and failure mode are all dictated.
 *
 * It also counts its own invocations, which is how the validator tests prove that a
 * disabled or unsupported rule is never run at all.
 *
 * @since 1.0.0
 */
final class Fake_Rule extends Abstract_Rule {

	/**
	 * Rule identifier.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private string $id;

	/**
	 * Which factory `check()` should use: pass, warn, fail or skip.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private string $outcome;

	/**
	 * Display priority.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	private int $priority;

	/**
	 * What `supports()` returns.
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	private bool $supported;

	/**
	 * Whether `check()` throws instead of returning.
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	private bool $throws;

	/**
	 * How many times `check()` has been called.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	private int $checks = 0;

	/**
	 * Severity this rule ships with.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private string $default_severity;

	/**
	 * Whether this rule ships enabled.
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	private bool $enabled_by_default;

	/**
	 * Construct the double.
	 *
	 * @since 1.0.0
	 *
	 * @param string $id                 Rule identifier.
	 * @param string $outcome            pass, warn, fail or skip.
	 * @param int    $priority           Display priority.
	 * @param bool   $supported          What `supports()` returns.
	 * @param bool   $throws             Whether `check()` throws.
	 * @param string $default_severity   Severity the rule ships with.
	 * @param bool   $enabled_by_default Whether the rule ships enabled.
	 */
	public function __construct( string $id, string $outcome = 'pass', int $priority = 50, bool $supported = true, bool $throws = false, string $default_severity = Severity::REQUIRED, bool $enabled_by_default = true ) {
		$this->id                 = $id;
		$this->outcome            = $outcome;
		$this->priority           = $priority;
		$this->supported          = $supported;
		$this->throws             = $throws;
		$this->default_severity   = $default_severity;
		$this->enabled_by_default = $enabled_by_default;
	}

	/**
	 * Severity the rule ships with.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_default_severity(): string {
		return $this->default_severity;
	}

	/**
	 * Whether the rule ships enabled.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function is_enabled_by_default(): bool {
		return $this->enabled_by_default;
	}

	/**
	 * Rule identifier.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_id(): string {
		return $this->id;
	}

	/**
	 * Rule label.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_label(): string {
		return 'Fake ' . $this->id;
	}

	/**
	 * Display priority.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	public function get_priority(): int {
		return $this->priority;
	}

	/**
	 * Whether the rule applies.
	 *
	 * @since 1.0.0
	 *
	 * @param Product_Context $context The product being validated.
	 * @return bool
	 */
	public function supports( Product_Context $context ): bool {
		unset( $context );

		return $this->supported;
	}

	/**
	 * Fix target, so the result shape assertions have something to check.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_fix_target(): array {
		return array(
			'selector' => '#' . $this->id,
			'label'    => 'Fix ' . $this->id,
			'panel'    => '',
		);
	}

	/**
	 * How many times `check()` has been called.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	public function get_check_count(): int {
		return $this->checks;
	}

	/**
	 * Produce the dictated outcome.
	 *
	 * @since 1.0.0
	 *
	 * @param Product_Context $context  The product being validated.
	 * @param Settings        $settings The merchant's configuration.
	 * @return Rule_Result
	 * @throws RuntimeException When the double was built to throw.
	 */
	public function check( Product_Context $context, Settings $settings ): Rule_Result {
		unset( $context, $settings );

		++$this->checks;

		if ( $this->throws ) {
			throw new RuntimeException( 'Fake rule failure.' );
		}

		switch ( $this->outcome ) {
			case 'fail':
				return $this->fail( 'Fake failure.', array( 'count' => 1 ) );
			case 'warn':
				return $this->warn( 'Fake warning.', array( 'count' => 2 ) );
			case 'skip':
				return $this->skip( 'Fake skip.' );
			default:
				return $this->pass();
		}
	}
}
