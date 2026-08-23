<?php
/**
 * Action Button Field Type
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Attributes;
use ArrayPress\FieldKit\Field;
use ArrayPress\FieldKit\Utils\Runtime;

/**
 * A button that runs a registered action over REST.
 *
 * `type="button"`, so it never submits the surrounding form by accident —
 * the default for a button inside a form is submit, which is a common and
 * confusing bug. The result is announced through a polite live region.
 */
final class ActionButtonType extends AbstractType {

	/**
	 * Render the button and its status region.
	 *
	 * @param Field      $field      The field.
	 * @param Attributes $attributes Prepared attributes.
	 *
	 * @return string
	 */
	public function render( Field $field, Attributes $attributes ): string {
		$attributes->set( 'type', 'button' );
		$attributes->add_class(
			'button',
			(string) $field->get( 'variant', 'secondary' ) === 'primary' ? 'button-primary' : 'button-secondary',
			'field-kit__action'
		);
		// The registered name, not the local one: the endpoint resolves
		// against a server-side registry, so a button naming anything else
		// reaches nothing.
		$names = (array) $field->get( 'action_names', [] );

		$attributes->set( 'data-action', (string) ( $names['run'] ?? $field->get( 'action', '' ) ) );
		$attributes->set( 'data-endpoint', rest_url( Runtime::rest_namespace() . '/action' ) );
		$attributes->set( 'data-nonce', wp_create_nonce( 'wp_rest' ) );
		$attributes->remove( 'name' );

		if ( $field->has( 'confirm' ) ) {
			$attributes->set( 'data-confirm', (string) $field->get( 'confirm' ) );
		}

		return sprintf(
			'<div class="field-kit__action-wrap"><button%s>%s</button>' .
			'<span class="spinner field-kit__action-spinner"></span>' .
			'<span class="field-kit__action-status" aria-live="polite"></span></div>',
			$attributes->render(),
			esc_html( (string) $field->get( 'button_label', $field->label() ) )
		);
	}

	/**
	 * A button stores nothing.
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
