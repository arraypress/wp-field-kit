<?php
/**
 * Post Relational Type
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Field;

/**
 * A searchable post reference.
 */
class PostType extends AbstractRelationalType {

	/**
	 * The search source.
	 *
	 * @return string
	 */
	protected function source(): string {
		return 'post';
	}

	/**
	 * Restrict the search to the configured post types.
	 *
	 * @param Field $field The field.
	 *
	 * @return array<string, scalar>
	 */
	protected function search_args( Field $field ): array {
		return [ 'post_type' => (string) $field->get( 'post_type', 'post' ) ];
	}

	/**
	 * Resolve titles for the selected ids.
	 *
	 * @param int[] $ids   Selected ids.
	 * @param Field $field The field.
	 *
	 * @return array<string, string>
	 */
	protected function resolve_labels( array $ids, Field $field ): array {
		$labels = [];

		foreach ( $ids as $id ) {
			$title = get_the_title( $id );

			if ( '' !== $title ) {
				$labels[ (string) $id ] = $title;
			}
		}

		return $labels;
	}
}
