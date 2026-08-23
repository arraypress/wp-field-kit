<?php
/**
 * User Relational Type
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Field;

/**
 * A searchable user reference.
 */
final class UserType extends AbstractRelationalType {

	/**
	 * The search source.
	 *
	 * @return string
	 */
	protected function source(): string {
		return 'user';
	}

	/**
	 * Restrict the search to the configured role.
	 *
	 * @param Field $field The field.
	 *
	 * @return array<string, scalar>
	 */
	protected function search_args( Field $field ): array {
		return $field->has( 'role' ) ? [ 'role' => (string) $field->get( 'role' ) ] : [];
	}

	/**
	 * Resolve display names for the selected ids.
	 *
	 * @param int[] $ids   Selected ids.
	 * @param Field $field The field.
	 *
	 * @return array<string, string>
	 */
	protected function resolve_labels( array $ids, Field $field ): array {
		$labels = [];

		foreach ( $ids as $id ) {
			$user = get_userdata( $id );

			if ( $user ) {
				$labels[ (string) $id ] = $user->display_name . ' (' . $user->user_email . ')';
			}
		}

		return $labels;
	}
}
