<?php
/**
 * Code Select Base
 *
 * @package     ArrayPress\FieldKit
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.2.0
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Field;

/**
 * A select whose values are ISO codes.
 *
 * A code is the same code whatever case it arrives in: "gb" from a query
 * string, "Gbp" from an import, "GB" from the form. The option list is the
 * allow-list, and it is keyed the way the standards write the codes, in
 * upper case -- so a value is raised to match before it is checked, rather
 * than rejected for its case and stored as nothing.
 */
abstract class AbstractCodeType extends SelectType {

	/**
	 * Coerce a submitted value, upper-cased first.
	 *
	 * @param mixed $value Raw submitted value.
	 * @param Field $field The field.
	 *
	 * @return mixed
	 */
	public function sanitize( mixed $value, Field $field ): mixed {
		return parent::sanitize( $this->uppercase( $value ), $field );
	}

	/**
	 * Upper-case a value, or each value of a list.
	 *
	 * Only what is a string. Anything else is left for the parent to refuse
	 * in its own way.
	 *
	 * @param mixed $value Raw submitted value.
	 *
	 * @return mixed
	 */
	private function uppercase( mixed $value ): mixed {
		if ( is_array( $value ) ) {
			return array_map( fn( mixed $one ): mixed => $this->uppercase( $one ), $value );
		}

		return is_string( $value ) ? strtoupper( trim( $value ) ) : $value;
	}
}
