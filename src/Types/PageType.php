<?php
/**
 * Page Relational Type
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Field;

/**
 * A searchable page reference — a post reference fixed to the page type.
 */
final class PageType extends PostType {

	/**
	 * Always pages.
	 *
	 * @param Field $field The field.
	 *
	 * @return array<string, scalar>
	 */
	protected function search_args( Field $field ): array {
		return [ 'post_type' => 'page' ];
	}
}
