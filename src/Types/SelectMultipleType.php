<?php
/**
 * Multiple Select Field Type
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Field;

/**
 * A select that accepts more than one value.
 *
 * Kept as its own type because config written as `'type' => 'select_multiple'`
 * reads better than a select carrying a flag, and because both predecessor
 * spellings have to keep resolving.
 */
final class SelectMultipleType extends SelectType {

	/**
	 * Always multiple, whatever the config says.
	 *
	 * @param Field $field The field.
	 *
	 * @return bool
	 */
	protected function is_multiple( Field $field ): bool {
		return true;
	}
}
