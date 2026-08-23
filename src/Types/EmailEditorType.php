<?php
/**
 * Email Editor Field Type
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Attributes;
use ArrayPress\FieldKit\Field;
use ArrayPress\FieldKit\Renderer;
use ArrayPress\FieldKit\Utils\Runtime;

/**
 * A subject line and body, with preview and test-send actions.
 *
 * Composed from existing types rather than hand-rolled: the subject is a
 * text field and the body a WYSIWYG, both built through the ordinary renderer
 * so both inherit the same labelling and associations as any other field.
 *
 * The available merge tags are rendered as a real list rather than a
 * paragraph of comma-separated codes, so they can be navigated one at a time
 * instead of read as one run-on string.
 */
final class EmailEditorType extends AbstractNestedType {

	/**
	 * Config defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return [
			'fields' => [
				'subject' => [
					'type'  => 'text',
					'label' => 'Subject',
				],
				'body'    => [
					'type'  => 'wysiwyg',
					'label' => 'Body',
				],
			],
		];
	}

	/**
	 * Render the parts, the tag list and the actions.
	 *
	 * @param Field      $field      The field.
	 * @param Attributes $attributes Prepared attributes.
	 *
	 * @return string
	 */
	public function render( Field $field, Attributes $attributes ): string {
		$values = is_array( $field->value() ) ? $field->value() : [];

		return sprintf(
			'<div class="field-kit__email-editor">%s%s%s</div>',
			$this->render_children( $field, $values, $field->input_name() ),
			$this->render_tags( $field ),
			$this->render_actions( $field )
		);
	}

	/**
	 * The merge tags this email accepts.
	 *
	 * @param Field $field The field.
	 *
	 * @return string
	 */
	private function render_tags( Field $field ): string {
		$tags = (array) $field->get( 'tags', [] );

		if ( [] === $tags ) {
			return '';
		}

		$items = '';

		foreach ( $tags as $tag => $description ) {
			$items .= sprintf(
				'<li><code>%s</code> <span class="description">%s</span></li>',
				esc_html( is_string( $tag ) ? $tag : (string) $description ),
				esc_html( is_string( $tag ) ? (string) $description : '' )
			);
		}

		return sprintf(
			'<div class="field-kit__email-tags"><h4>%s</h4><ul>%s</ul></div>',
			esc_html__( 'Available tags', 'arraypress' ),
			$items
		);
	}

	/**
	 * The preview and test-send buttons.
	 *
	 * @param Field $field The field.
	 *
	 * @return string
	 */
	private function render_actions( Field $field ): string {
		$buttons = '';

		foreach (
			[
				'preview' => __( 'Preview', 'arraypress' ),
				'test'    => __( 'Send a test', 'arraypress' ),
			] as $action => $label
		) {
			$button = new Attributes();
			$button->set( 'type', 'button' );
			$button->add_class( 'button', 'field-kit__email-action' );
			$names = (array) $field->get( 'action_names', [] );

			$button->set( 'data-action', (string) ( $names[ $action ] ?? '' ) );
			$button->set( 'data-payload-from', $field->input_id() );
			$button->set( 'data-endpoint', rest_url( Runtime::rest_namespace() . '/action' ) );
			$button->set( 'data-nonce', wp_create_nonce( 'wp_rest' ) );
			$button->set( 'data-field', $field->key() );

			$buttons .= sprintf( '<button%s>%s</button> ', $button->render(), esc_html( $label ) );
		}

		return sprintf(
			'<p class="field-kit__email-actions">%s<span class="spinner"></span>' .
			'<span class="field-kit__email-status" aria-live="polite"></span></p>',
			$buttons
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
