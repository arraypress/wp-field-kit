<?php
/**
 * Group Field Type
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Attributes;
use ArrayPress\FieldKit\Field;

/**
 * A fixed set of related fields stored under one key.
 *
 * Reported as grouped, so the renderer wraps it in a fieldset with a legend
 * rather than pointing a label at an element that does not exist — which is
 * what produced a group rendering as one empty text input.
 */
final class GroupType extends AbstractNestedType {

	/**
	 * Render the children.
	 *
	 * @param Field      $field      The field.
	 * @param Attributes $attributes Prepared attributes.
	 *
	 * @return string
	 */
	public function render( Field $field, Attributes $attributes ): string {
		$values = is_array( $field->value() ) ? $field->value() : [];

		return sprintf(
			'<div class="field-kit__group">%s</div>',
			$this->render_children( $field, $values, $field->input_name() )
		);
	}

	/**
	 * Coerce a submitted value.
	 *
	 * @param mixed $value Raw submitted value.
	 * @param Field $field The field.
	 *
	 * @return array<string, mixed>
	 */
	public function sanitize( mixed $value, Field $field ): array {
		return $this->sanitize_children( $field, $value );
	}

	/**
	 * Needs a fieldset and legend.
	 *
	 * @return bool
	 */
	public function is_grouped(): bool {
		return true;
	}
}
