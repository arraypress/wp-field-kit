<?php
/**
 * Licence Key Field Type
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Attributes;
use ArrayPress\FieldKit\Field;
use ArrayPress\FieldKit\Utils\Runtime;

/**
 * A licence key with an activate or deactivate action and a status.
 *
 * The status is a polite live region rather than a coloured dot: activation
 * outcome has to be conveyed by text, not by colour alone, and it changes
 * after the page has loaded so it needs announcing when it does.
 *
 * An activated key is masked, and the input becomes readonly rather than
 * disabled — a disabled input cannot be focused, so its value could not be
 * read by keyboard at all.
 */
final class LicenseType extends AbstractInputType {

	/**
	 * The HTML input type.
	 *
	 * @return string
	 */
	protected function input_type(): string {
		return 'text';
	}

	/**
	 * Render the key, the action and the status.
	 *
	 * @param Field      $field      The field.
	 * @param Attributes $attributes Prepared attributes.
	 *
	 * @return string
	 */
	public function render( Field $field, Attributes $attributes ): string {
		$active = (bool) $field->get( 'is_active', false );

		$attributes->add_class( 'regular-text', 'code', 'field-kit__license-key' );
		$attributes->set_if( $active, 'readonly', true );

		$button = new Attributes();
		$button->set( 'type', 'button' );
		$button->add_class( 'button', 'field-kit__license-action' );
		$names  = (array) $field->get( 'action_names', [] );
		$local  = $active ? 'deactivate' : 'activate';

		$button->set( 'data-action', (string) ( $names[ $local ] ?? '' ) );
		$button->set( 'data-payload-from', $field->input_id() );
		$button->set( 'data-endpoint', rest_url( Runtime::rest_namespace() . '/action' ) );
		$button->set( 'data-nonce', wp_create_nonce( 'wp_rest' ) );
		$button->set( 'data-field', $field->key() );

		return sprintf(
			'<div class="field-kit__license">%s <button%s>%s</button>' .
			'<span class="spinner"></span>' .
			'<p class="field-kit__license-status description" aria-live="polite">%s</p></div>',
			parent::render( $field, $attributes ),
			$button->render(),
			esc_html( $active ? __( 'Deactivate', 'arraypress' ) : __( 'Activate', 'arraypress' ) ),
			esc_html( (string) $field->get( 'status_message', '' ) )
		);
	}

	/**
	 * A masked value once the licence is active.
	 *
	 * @param Field $field The field.
	 *
	 * @return string
	 */
	protected function render_value( Field $field ): string {
		$key = (string) $field->value();

		if ( '' === $key || ! $field->get( 'is_active', false ) ) {
			return $key;
		}

		return str_repeat( '*', max( 0, strlen( $key ) - 4 ) ) . substr( $key, -4 );
	}

	/**
	 * Coerce a submitted value.
	 *
	 * A masked value is never written back over the real key.
	 *
	 * @param mixed $value Raw submitted value.
	 * @param Field $field The field.
	 *
	 * @return string
	 */
	public function sanitize( mixed $value, Field $field ): string {
		$value = sanitize_text_field( (string) $value );

		return str_contains( $value, '****' ) ? (string) $field->raw_value() : $value;
	}

	/**
	 * Does not fit an inline row.
	 *
	 * Activating a licence from a list-table row, against whichever posts happen to be selected, is not a thing anyone means to do.
	 *
	 * @return bool
	 */
	public function supports_inline(): bool {
		return false;
	}
}
