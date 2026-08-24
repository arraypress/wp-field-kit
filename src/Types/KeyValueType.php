<?php
/**
 * Key/Value Field Type
 *
 * @package     ArrayPress\FieldKit
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

/**
 * A list of named values: metadata, headers, custom attributes.
 *
 * A table repeater with its two columns already decided, in the same way the
 * files type has its two — because every consumer that wanted this wrote the
 * same pair, and one of them wrote it three times.
 *
 * Stored as rows of `[ 'key' => …, 'value' => … ]` rather than as a map. A
 * map cannot hold two entries with the same key, cannot keep an order, and
 * loses a row the moment someone is halfway through typing a key that already
 * exists — all three of which happen while a person is editing.
 */
final class KeyValueType extends RepeaterType {

	/**
	 * The type's id.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'key_value';
	}

	/**
	 * Two columns, a table, and wording about entries.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return array_merge(
			parent::defaults(),
			[
				'layout'      => 'table',
				'add_label'   => __( 'Add entry', 'arraypress' ),
				'empty_label' => __( 'No entries yet.', 'arraypress' ),
				'fields'      => [
					'key'   => [
						'type'         => 'text',
						'label'        => __( 'Key', 'arraypress' ),
						'column_width' => '40%',
					],
					'value' => [
						'type'  => 'text',
						'label' => __( 'Value', 'arraypress' ),
					],
				],
			]
		);
	}
}
