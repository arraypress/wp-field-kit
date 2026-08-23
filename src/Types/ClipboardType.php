<?php
/**
 * Clipboard Field Type
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Attributes;
use ArrayPress\FieldKit\Field;

/**
 * A read-only value with a copy button.
 *
 * The input is readonly rather than disabled: a disabled input cannot be
 * focused, so its contents cannot be read or selected by keyboard at all.
 * The copy result is announced through a polite live region, since a button
 * that silently succeeds gives no feedback to anyone not watching it.
 */
final class ClipboardType extends AbstractType {

	/**
	 * Render the value and the copy button.
	 *
	 * @param Field      $field      The field.
	 * @param Attributes $attributes Prepared attributes.
	 *
	 * @return string
	 */
	public function render( Field $field, Attributes $attributes ): string {
		$attributes->set( 'type', 'text' );
		$attributes->set( 'value', (string) $field->value() );
		$attributes->set( 'readonly', true );
		$attributes->add_class( 'regular-text', 'code', 'field-kit__clipboard-value' );

		$button = new Attributes();
		$button->set( 'type', 'button' );
		$button->add_class( 'button', 'field-kit__clipboard-copy' );
		$button->set(
			'aria-label',
			sprintf(
				/* translators: %s: field label */
				__( 'Copy %s to the clipboard', 'arraypress' ),
				$field->label()
			)
		);

		// The success span is core's own shape, from the media modal's
		// copy-to-clipboard control: a message beside the button that shows
		// briefly. Visible feedback matters as much as the announced kind —
		// a button that silently succeeds leaves everyone guessing.
		return sprintf(
			'<div class="field-kit__clipboard"><input%s /> <button%s>%s</button>' .
			'<span class="success hidden field-kit__clipboard-success" aria-hidden="true">%s</span>' .
			'<span class="field-kit__clipboard-status screen-reader-text" aria-live="polite"></span></div>',
			$attributes->render(),
			$button->render(),
			esc_html__( 'Copy', 'arraypress' ),
			esc_html__( 'Copied!', 'arraypress' )
		);
	}

	/**
	 * The displayed value is generated, not submitted.
	 *
	 * @return bool
	 */
	public function stores_value(): bool {
		return false;
	}

	/**
	 * Nothing to sanitize.
	 *
	 * @param mixed $value Raw submitted value.
	 * @param Field $field The field.
	 *
	 * @return null
	 */
	public function sanitize( mixed $value, Field $field ): mixed {
		return null;
	}
}
