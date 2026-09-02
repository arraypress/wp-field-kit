<?php
/**
 * Percentage Field Type
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

/**
 * A share of something, from nought to a hundred.
 *
 * A number field configured three ways at once, which is what every caller
 * was doing by hand: a minimum of 0, a maximum of 100, and a "%" in the box
 * so the unit is visible without the label having to say "(percent)". The
 * clamp and the schema are the number field's own, so 150 saves as 100 and
 * -5 as 0 without a sanitizer of this type's.
 *
 * Whole numbers by default. A discount is "10%", not "10.00%", and because
 * the step decides the stored type a fractional one would turn every
 * percentage into a float. A caller that needs "2.5%" sets `step => 0.01`
 * and gets one. The width is the number field's small one: three digits.
 */
final class PercentageType extends NumberType {

	/**
	 * Config defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return [
			'min'    => 0,
			'max'    => 100,
			'step'   => 1,
			'suffix' => '%',
		];
	}
}
