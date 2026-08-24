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
 * The options come from the field, not from a server. The combobox filters the
 * list it was given, so a long-but-fixed list — currencies, countries, post
 * statuses — is searchable without an endpoint or a round trip. That is the
 * difference from `ajax`, which asks an endpoint and cannot show you the whole
 * list at all.
 *
 * It needs to be its own type rather than an alias of `select`: aliasing it
 * gave a plain native dropdown, so every field written this way silently lost
 * its search box.
 *
 * The settings library spelled it `select2`, after the library an enhanced
 * select used to be built on. That library is gone from every one of these
 * repositories, so the name went with it — but it is still an accepted
 * spelling, because a type id nobody recognises is a fatal and a type id that
 * quietly resolves to a plain select is worse.
 */
final class EnhancedSelectType extends SelectType {

	/**
	 * The type's id.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'enhanced_select';
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
