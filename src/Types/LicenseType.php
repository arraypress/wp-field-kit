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

		// Deactivating is the destructive half, and is drawn the way core
		// draws a destructive bordered button. Activating is an ordinary
		// secondary action — the state is told by the badge beside it and by
		// the status text, not by making the button green, because a control
		// coloured for the state it is *in* rather than the action it
		// performs is the classic way to get a licence deactivated by
		// accident.
		$button->set_if( $active, 'class', 'field-kit__button--delete' );

		$names = (array) $field->get( 'action_names', [] );
		$local = $active ? 'deactivate' : 'activate';

		$button->set( 'data-action', (string) ( $names[ $local ] ?? '' ) );

		// Both names, so the button can become the other one without a page
		// load. An action that succeeds and leaves the field showing the
		// state it was in before is an action that looks like it failed.
		$button->set_if( isset( $names['activate'] ), 'data-action-activate', (string) ( $names['activate'] ?? '' ) );
		$button->set_if( isset( $names['deactivate'] ), 'data-action-deactivate', (string) ( $names['deactivate'] ?? '' ) );

		$button->set( 'data-label-activate', __( 'Activate', 'arraypress' ) );
		$button->set( 'data-label-deactivate', __( 'Deactivate', 'arraypress' ) );
		$button->set( 'data-endpoint', rest_url( Runtime::rest_namespace() . '/action' ) );
		$button->set( 'data-nonce', wp_create_nonce( 'wp_rest' ) );
		$button->set( 'data-field', $field->key() );

		// Three rows, not one line. The key, the button, the state and the
		// status message all sat on a single flex row, which put a sentence
		// of explanation out past the right-hand edge of everything else on
		// the screen — and the state badge somewhere between the button and
		// the sentence, belonging to neither.
		return sprintf(
			'<div class="field-kit__license"' .
			' data-label-active="' . esc_attr__( 'Active', 'arraypress' ) . '"' .
			' data-label-inactive="' . esc_attr__( 'Not active', 'arraypress' ) . '">' .
			'<div class="field-kit__license-control">%s<button%s>%s</button><span class="spinner"></span></div>' .
			'%s' .
			'<p class="field-kit__license-status description" aria-live="polite">%s</p>' .
			'</div>',
			parent::render( $field, $attributes ),
			$button->render(),
			esc_html( $active ? __( 'Deactivate', 'arraypress' ) : __( 'Activate', 'arraypress' ) ),
			$this->render_state( $field, $active ),
			esc_html( (string) $field->get( 'status_message', '' ) )
		);
	}

	/**
	 * The badge saying whether the licence is active, and how far it is used.
	 *
	 * Text and a shape, not a colour alone: "activated" is the sort of thing
	 * a plugin conveys with a green dot and nothing else, which conveys
	 * nothing to anyone who cannot see the green.
	 *
	 * `sites` is optional and takes the shape [ used, total ] — "1 of 3 sites"
	 * — because a licence that is active *here* and exhausted everywhere else
	 * is a different situation from one with room to spare, and the field is
	 * where someone looks to find that out.
	 *
	 * @param Field $field  The field.
	 * @param bool  $active Whether the licence is active.
	 *
	 * @return string
	 */
	private function render_state( Field $field, bool $active ): string {
		$sites = (array) $field->get( 'sites', [] );
		$usage = '';

		if ( 2 === count( $sites ) ) {
			$usage = sprintf(
				/* translators: 1: sites the licence is active on, 2: sites it allows */
				__( '%1$s of %2$s sites', 'arraypress' ),
				number_format_i18n( (int) $sites[0] ),
				number_format_i18n( (int) $sites[1] )
			);
		}

		// The seat count is a template as well as a value, so the script can
		// rewrite it with new numbers without knowing how the sentence is
		// worded — or which language it is in.
		return sprintf(
			'<div class="field-kit__license-meta" data-sites-template="%s">' .
			'<span class="field-kit__license-state field-kit__license-state--%s">' .
			'<span class="dashicons dashicons-%s" aria-hidden="true"></span>' .
			'<span class="field-kit__license-state-text">%s</span></span>' .
			'<span class="field-kit__license-sites"%s>%s</span></div>',
			esc_attr(
				sprintf(
					/* translators: 1: sites the licence is active on, 2: sites it allows */
					__( '%1$s of %2$s sites', 'arraypress' ),
					'%1$s',
					'%2$s'
				)
			),
			$active ? 'active' : 'inactive',
			$active ? 'yes-alt' : 'marker',
			esc_html( $active ? __( 'Active', 'arraypress' ) : __( 'Not active', 'arraypress' ) ),
			'' === $usage ? ' hidden' : '',
			esc_html( $usage )
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
		$value = sanitize_text_field( $this->scalar( $value ) );

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

	/**
	 * The configuration keys this type reads.
	 *
	 * @return string[]
	 */
	public function config_keys(): array {
		return array_merge(
			parent::config_keys(),
			[ 'action_names', 'is_active', 'sites', 'status_message' ]
		);
	}
}
