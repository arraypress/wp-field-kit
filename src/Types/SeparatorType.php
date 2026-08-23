<?php
/**
 * Separator Field Type
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Attributes;
use ArrayPress\FieldKit\Field;

/**
 * A horizontal rule between groups of fields.
 *
 * Decorative, so it is hidden from assistive technology: announcing
 * "separator" between every group is noise, and the grouping is already
 * carried by headings and fieldsets.
 */
final class SeparatorType extends AbstractLayoutType {

	/**
	 * Render the rule.
	 *
	 * @param Field      $field      The field.
	 * @param Attributes $attributes Prepared attributes.
	 *
	 * @return string
	 */
	public function render( Field $field, Attributes $attributes ): string {
		return '<hr class="field-kit__separator" aria-hidden="true" />';
	}
}
