<?php
/**
 * Button Group Field Type
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

/**
 * A segmented control.
 *
 * Rendered as real radio inputs styled as buttons rather than as `<button>`
 * elements with `aria-pressed`. Radios give arrow-key navigation, a single
 * tab stop for the group and correct announcement as "N of M" at no cost;
 * rebuilding that on buttons is where segmented controls usually become
 * keyboard traps.
 */
final class ButtonGroupType extends RadioType {

	/**
	 * Class stem for the wrapper.
	 *
	 * @return string
	 */
	protected function wrapper_class(): string {
		// core's own .button-group, which already styles a segmented row.
		return 'button-group';
	}
}
