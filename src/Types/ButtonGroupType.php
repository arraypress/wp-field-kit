<?php
/**
 * Button Group Field Type
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Attributes;
use ArrayPress\FieldKit\Field;

/**
 * A segmented control.
 *
 * Rendered as real radio inputs styled as buttons rather than as `<button>`
 * elements with `aria-pressed`. Radios give arrow-key navigation, a single
 * tab stop for the group and correct announcement as "N of M" at no cost;
 * rebuilding that on buttons is where segmented controls usually become
 * keyboard traps.
 */
final class ButtonGroupType extends RadioType {

	/**
	 * Class stem for the wrapper.
	 *
	 * @return string
	 */
	protected function wrapper_class(): string {
		// Deliberately not core's .button-group. That rule sets font-size: 0
		// to collapse the whitespace between inline-block children and
		// relies on .button restoring it — these are labels, not buttons, so
		// they inherited zero-size text and the control rendered as a stack
		// of thin coloured lines with nothing readable in it. The segmented
		// look is a dozen lines of CSS; borrowing a class whose contract did
		// not fit was never worth that.
		return 'field-kit__button-group';
	}

	/**
	 * Render one option.
	 *
	 * The only type that still pairs a label to its input by id rather than
	 * wrapping it. A segmented control is drawn by styling the label of the
	 * checked radio, and `input:checked + label` needs the two to be
	 * siblings — a wrapping label has no sibling to select. The id is derived
	 * from the field's own input id plus the option value, so it stays unique
	 * inside a repeater row.
	 *
	 * @param Attributes $option The option's attributes.
	 * @param string     $label  The option's label.
	 * @param Field      $field  The field.
	 *
	 * @return string
	 */
	protected function render_option( Attributes $option, string $label, Field $field ): string {
		$id = sanitize_key( $field->input_id() . '_' . (string) $option->get( 'value' ) );

		$option->set( 'id', $id );

		return sprintf(
			'<div class="field-kit__button-group-option"><input%s /><label for="%s">%s</label></div>',
			$option->render(),
			esc_attr( $id ),
			esc_html( $label )
		);
	}
}
