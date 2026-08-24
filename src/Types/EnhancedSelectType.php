<?php
/**
 * Enhanced Select Field Type
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Field;

/**
 * A searchable dropdown over its own option list.
 *
 * The type `select2` used to mean in the settings library, and the reason it
 * needs to be its own class rather than an alias: aliasing it to `select` gave
 * a plain native dropdown, so every field written as `select2` silently lost
 * its search box.
 *
 * The options come from the field, not from a server. The combobox filters the
 * list it was given, so a long-but-fixed list — currencies, countries, post
 * statuses — is searchable without an endpoint or a round trip.
 */
final class EnhancedSelectType extends SelectType {

	/**
	 * The type's id.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'select2';
	}

	/**
	 * Always searchable — that is what this type is.
	 *
	 * @param Field $field The field.
	 *
	 * @return bool
	 */
	protected function uses_enhanced_ui( Field $field ): bool {
		return true;
	}
}
