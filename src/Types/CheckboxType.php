<?php
/**
 * Checkbox Field Type
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Attributes;
use ArrayPress\FieldKit\Field;
use ArrayPress\FieldKit\Support\Markup;

/**
 * A single checkbox with its text beside it.
 */
class CheckboxType extends AbstractType {

	/**
	 * Render the checkbox.
	 *
	 * A hidden input of the same name precedes it. An unchecked box sends
	 * nothing at all, which is indistinguishable from the field not being on
	 * the form — so without this, unchecking could never be saved.
	 *
	 * @param Field      $field      The field.
	 * @param Attributes $attributes Prepared attributes.
	 *
	 * @return string
	 */
	public function render( Field $field, Attributes $attributes ): string {
		$name = (string) $attributes->get( 'name' );

		$attributes->set( 'type', 'checkbox' );
		$attributes->set( 'value', '1' );
		$attributes->add_class( 'field-kit__checkbox' );
		$attributes->set_if( $this->is_checked( $field ), 'checked', true );

		$this->apply_role( $attributes, $field );

		// options-general.php's exact shape: the input inside the label, the
		// text directly after it, no wrapping span. A span here was what let
		// `.field-kit__field > label` bold the text, which core's never is.
		return sprintf(
			'<input type="hidden" name="%s" value="0" />' .
			'<label class="field-kit__checkbox-label" for="%s"><input%s /> %s%s</label>',
			esc_attr( $name ),
			esc_attr( $field->input_id() ),
			$attributes->render(),
			esc_html( $this->checkbox_label( $field ) ),
			// The renderer writes no label around a self-labelling control,
			// so the required marker has to come from here — via the same
			// shared fragment, so the two never diverge.
			Markup::required_marker( $field->is_required() )
		);
	}

	/**
	 * Add any role this variant needs.
	 *
	 * @param Attributes $attributes Attributes being built.
	 * @param Field      $field      The field.
	 *
	 * @return void
	 */
	protected function apply_role( Attributes $attributes, Field $field ): void {
	}

	/**
	 * The text shown beside the box.
	 *
	 * @param Field $field The field.
	 *
	 * @return string
	 */
	protected function checkbox_label( Field $field ): string {
		return (string) $field->get( 'checkbox_label', $field->label() );
	}

	/**
	 * Whether the box is ticked.
	 *
	 * @param Field $field The field.
	 *
	 * @return bool
	 */
	protected function is_checked( Field $field ): bool {
		return in_array( $field->value(), [ 1, '1', true, 'yes', 'on' ], true );
	}

	/**
	 * Coerce a submitted value.
	 *
	 * @param mixed $value Raw submitted value.
	 * @param Field $field The field.
	 *
	 * @return int
	 */
	public function sanitize( mixed $value, Field $field ): int {
		return in_array( $value, [ 1, '1', true, 'yes', 'on' ], true ) ? 1 : 0;
	}

	/**
	 * The control carries its own text.
	 *
	 * @return bool
	 */
	public function is_self_labelling(): bool {
		return true;
	}

	/**
	 * Stored as 0 or 1, not as a boolean.
	 *
	 * sanitize() returns an int, and a schema saying `boolean` would make
	 * REST refuse the very value the admin writes.
	 *
	 * @param Field $field The field.
	 *
	 * @return array<string, mixed>
	 */
	public function schema( Field $field ): array {
		return [
			'type' => 'integer',
			'enum' => [ 0, 1 ],
		];
	}

	/**
	 * Fits an inline row.
	 *
	 * A box and its label, which is one line.
	 *
	 * @return bool
	 */
	public function supports_inline(): bool {
		return true;
	}
}
