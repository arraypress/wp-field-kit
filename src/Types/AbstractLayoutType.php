<?php
/**
 * Base Layout Type
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Field;

/**
 * Types that draw something and store nothing.
 *
 * Saying so once here keeps "is this field actually a field?" out of every
 * save path, which is where the predecessor libraries leaked separators into
 * the options table.
 */
abstract class AbstractLayoutType extends AbstractType {

	/**
	 * Nothing to store.
	 *
	 * @return bool
	 */
	public function stores_value(): bool {
		return false;
	}

	/**
	 * These draw their own heading and have no control behind it.
	 *
	 * The renderer must not add a <label for>: there is no input with that
	 * id, and a label pointing at nothing is announced as an orphan rather
	 * than being silently ignored.
	 *
	 * @return bool
	 */
	public function is_self_labelling(): bool {
		return true;
	}

	/**
	 * Nothing to sanitize.
	 *
	 * @param mixed $value Raw submitted value.
	 * @param Field $field The field.
	 *
	 * @return null
	 */
	public function sanitize( mixed $value, Field $field ): mixed {
		return null;
	}

	/**
	 * A heading, a notice or a rule has no label to sit beside.
	 *
	 * @return bool
	 */
	public function spans_row(): bool {
		return true;
	}
}
