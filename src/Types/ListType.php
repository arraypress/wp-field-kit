<?php
/**
 * List Field Type
 *
 * @package     ArrayPress\FieldKit
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Field;

/**
 * An ordered list of short strings, each on its own row.
 *
 * A repeater of one column, and stored as the flat array anyone would expect:
 * `[ 'One year of updates', 'Priority support' ]` rather than
 * `[ [ 'item' => 'One year of updates' ], ... ]`. That difference is the
 * whole reason this is a type instead of a note in the documentation — a
 * feature list, a set of bullet points, a list of included things is read
 * back by a template that wants strings, and asking every consumer to reach
 * through a one-key array is how a repeater ends up being reimplemented.
 *
 * `tags` is the other way to collect several short strings and is a different
 * control: one input, separated by commas, no order to speak of. This is for
 * a list whose order is the point and whose entries are sentences.
 */
final class ListType extends RepeaterType {

	/**
	 * The key each row's single control is stored under, internally.
	 *
	 * @var string
	 */
	private const COLUMN = 'item';

	/**
	 * The type's id.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'list';
	}

	/**
	 * One column, stacked, worded for entries rather than rows.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return array_merge(
			parent::defaults(),
			[
				'add_label'   => __( 'Add', 'arraypress' ),
				'empty_label' => __( 'Nothing yet.', 'arraypress' ),
				'fields'      => [
					self::COLUMN => [
						'type'  => 'text',
						'label' => __( 'Item', 'arraypress' ),
					],
				],
			]
		);
	}

	/**
	 * Wrap each stored string in the row shape the repeater draws.
	 *
	 * @param Field $field The field.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function rows( Field $field ): array {
		$value = $field->value();

		if ( ! is_array( $value ) ) {
			return [];
		}

		$rows = [];

		foreach ( $value as $entry ) {
			// Already a row — a value that has been through a plain repeater,
			// or a consumer who stored it that way. Taken as it is rather
			// than nested a second time.
			$rows[] = is_array( $entry ) ? $entry : [ self::COLUMN => $entry ];
		}

		return $rows;
	}

	/**
	 * Store the flat array, not the rows.
	 *
	 * @param mixed $value Raw submitted value.
	 * @param Field $field The field.
	 *
	 * @return string[]
	 */
	public function sanitize( mixed $value, Field $field ): array {
		$entries = [];

		foreach ( parent::sanitize( $value, $field ) as $row ) {
			$entry = $row[ self::COLUMN ] ?? '';

			if ( '' !== $entry ) {
				$entries[] = $entry;
			}
		}

		return $entries;
	}

	/**
	 * A list of strings, which is what a template reads back.
	 *
	 * @param Field $field The field.
	 *
	 * @return array<string, mixed>
	 */
	public function schema( Field $field ): array {
		return [
			'type'  => 'array',
			'items' => [ 'type' => 'string' ],
		];
	}
}
