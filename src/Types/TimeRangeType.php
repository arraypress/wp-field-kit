<?php
/**
 * Time Range Field Type
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

/**
 * A start and end time.
 */
final class TimeRangeType extends AbstractRangePairType {

	/**
	 * The HTML input type.
	 *
	 * @return string
	 */
	protected function input_type(): string {
		return 'time';
	}
}
