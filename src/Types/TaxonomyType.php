<?php
/**
 * Taxonomy Relational Type
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Field;

/**
 * A searchable term reference.
 *
 * `term` is accepted as an alias: post-fields called it that and
 * setting-fields called it `taxonomy`, and both spellings appear in existing
 * config.
 */
final class TaxonomyType extends AbstractRelationalType {

	/**
	 * The search source.
	 *
	 * @return string
	 */
	protected function source(): string {
		return 'term';
	}

	/**
	 * Restrict the search to the configured taxonomy.
	 *
	 * @param Field $field The field.
	 *
	 * @return array<string, scalar>
	 */
	protected function search_args( Field $field ): array {
		return [ 'taxonomy' => (string) $field->get( 'taxonomy', 'category' ) ];
	}

	/**
	 * Resolve names for the selected ids.
	 *
	 * @param int[] $ids   Selected ids.
	 * @param Field $field The field.
	 *
	 * @return array<string, string>
	 */
	protected function resolve_labels( array $ids, Field $field ): array {
		$labels = [];

		foreach ( $ids as $id ) {
			$term = get_term( $id );

			if ( $term && ! is_wp_error( $term ) ) {
				$labels[ (string) $id ] = $term->name;
			}
		}

		return $labels;
	}
}
