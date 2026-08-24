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
use ArrayPress\FieldKit\Utils\Runtime;

/**
 * One email a plugin sends, as a collapsible panel.
 *
 * A plugin rarely has one email. It has a handful — a receipt, a renewal
 * notice, an admin alert — each with a recipient, a subject, a heading and a
 * body, and each wanting to be read and edited on its own without the others
 * in the way. A row in a settings table is the wrong shape for that: it is
 * built for one control beside one label, and an email is four controls, a
 * tag reference and two actions.
 *
 * So it renders as a `postbox` — core's own panel, the shape every metabox on
 * every edit screen uses, collapsible from its header. That is why it spans
 * the row rather than sitting in a table cell, and why it labels itself: the
 * panel heading is the field's name, and a legend repeating it would announce
 * the email twice.
 *
 * Which parts appear is configuration. `recipient` and `heading` are off by
 * default because not every email has them — an admin notice has a fixed
 * recipient, a plain-text one has no heading — and an input for something the
 * sender ignores is worse than no input at all.
 */
final class EmailEditorType extends AbstractNestedType {

	/**
	 * Config defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return [
			'recipient' => false,
			'heading'   => false,
			'collapsed' => false,
			'tags'      => [],
			'fields'    => [],
		];
	}

	/**
	 * The parts this email is made of.
	 *
	 * Built from ordinary types rather than hand-rolled, so every part
	 * inherits the same labelling, sanitizing and associations as any other
	 * field. Overridable wholesale by passing `fields`, for an email that
	 * needs a part this does not know about.
	 *
	 * @param Field $field The field.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function parts( Field $field ): array {
		$configured = (array) $field->get( 'fields', [] );

		if ( [] !== $configured ) {
			return $configured;
		}

		$parts = [];

		if ( (bool) $field->get( 'recipient', false ) ) {
			$parts['recipient'] = [
				'type'        => 'text',
				'label'       => __( 'Recipient', 'arraypress' ),
				'placeholder' => 'name@example.com',
				'class'       => 'regular-text',
				'description' => __( 'Separate several addresses with commas.', 'arraypress' ),
			];
		}

		$parts['subject'] = [
			'type'  => 'text',
			'label' => __( 'Subject', 'arraypress' ),
			'class' => 'large-text',
		];

		if ( (bool) $field->get( 'heading', false ) ) {
			$parts['heading'] = [
				'type'        => 'text',
				'label'       => __( 'Heading', 'arraypress' ),
				'class'       => 'large-text',
				'description' => __( 'Shown at the top of the email, above the body.', 'arraypress' ),
			];
		}

		$parts['body'] = [
			'type'  => 'wysiwyg',
			'label' => __( 'Body', 'arraypress' ),
		];

		return $parts;
	}

	/**
	 * A copy of the field carrying its resolved parts.
	 *
	 * The parts are what `sub_fields()` reads, and rendering and sanitizing
	 * must agree on them exactly or a part is drawn and never saved.
	 *
	 * @param Field $field The field.
	 *
	 * @return Field
	 */
	private function with_parts( Field $field ): Field {
		return $field->with_config( [ 'fields' => $this->parts( $field ) ] );
	}

	/**
	 * Render the panel.
	 *
	 * @param Field      $field      The field.
	 * @param Attributes $attributes Prepared attributes.
	 *
	 * @return string
	 */
	public function render( Field $field, Attributes $attributes ): string {
		$values    = is_array( $field->value() ) ? $field->value() : [];
		$title_id  = $field->input_id() . '__title';
		$collapsed = (bool) $field->get( 'collapsed', false );

		$panel = new Attributes();
		$panel->set( 'id', $field->input_id() . '__panel' );
		$panel->add_class( 'postbox', 'field-kit__email' );
		$panel->set_if( $collapsed, 'class', 'closed' );

		// role="region" with a name is what core gives a metabox, and it is
		// what lets someone move between panels rather than tab through every
		// control in one. It is also what stands in for the label: there is
		// no single control here to point a <label for> at.
		$panel->set( 'role', 'region' );
		$panel->set( 'aria-labelledby', $title_id );

		// The renderer built the description association for a control this
		// field does not have, so the region takes it instead. Without this
		// the description sits below the panel referenced by nothing.
		$described = (string) $attributes->get( 'aria-describedby' );

		$panel->set_if( '' !== $described, 'aria-describedby', $described );

		return sprintf(
			'<div%s>%s<div class="inside">%s%s%s</div></div>',
			$panel->render(),
			$this->render_header( $field, $title_id, $collapsed ),
			$this->render_children( $this->with_parts( $field ), $values, $field->input_name() ),
			$this->render_tags( $field ),
			$this->render_actions( $field )
		);
	}

	/**
	 * The panel header, with core's own show/hide control.
	 *
	 * @param Field  $field     The field.
	 * @param string $title_id  Id of the heading.
	 * @param bool   $collapsed Whether the panel starts closed.
	 *
	 * @return string
	 */
	private function render_header( Field $field, string $title_id, bool $collapsed ): string {
		return sprintf(
			'<div class="postbox-header">' .
			'<h2 class="hndle" id="%1$s">%2$s</h2>' .
			'<div class="handle-actions hide-if-no-js">' .
			'<button type="button" class="handlediv field-kit__email-toggle" aria-expanded="%3$s" aria-describedby="%1$s">' .
			'<span class="screen-reader-text">%4$s</span>' .
			'<span class="toggle-indicator" aria-hidden="true"></span>' .
			'</button></div></div>',
			esc_attr( $title_id ),
			esc_html( '' !== $field->label() ? $field->label() : __( 'Email', 'arraypress' ) ),
			$collapsed ? 'false' : 'true',
			esc_html__( 'Show or hide panel', 'arraypress' )
		);
	}

	/**
	 * The merge tags this email accepts, as buttons that insert them.
	 *
	 * A list of codes to copy by hand is a list of codes to mistype. These
	 * insert at the cursor of whichever part was last focused, which is the
	 * only thing anyone was going to do with them.
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
			// Accepts a list of tags as well as a map of tag => description.
			$code = is_string( $tag ) ? $tag : (string) $description;
			$help = is_string( $tag ) ? (string) $description : '';

			$button = new Attributes();
			$button->set( 'type', 'button' );
			$button->add_class( 'button-link', 'field-kit__email-tag' );
			$button->set( 'data-tag', $code );

			$items .= sprintf(
				'<li><button%s><code>%s</code></button>%s</li>',
				$button->render(),
				esc_html( $code ),
				'' === $help ? '' : sprintf( ' <span class="description">%s</span>', esc_html( $help ) )
			);
		}

		return sprintf(
			'<div class="field-kit__email-tags">' .
			'<h3 class="field-kit__email-tags-title">%s</h3>' .
			'<p class="description">%s</p>' .
			'<ul class="field-kit__email-tag-list">%s</ul></div>',
			esc_html__( 'Merge tags', 'arraypress' ),
			esc_html__( 'Choose a tag to insert it where you were last typing.', 'arraypress' ),
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
		$names   = (array) $field->get( 'action_names', [] );
		$buttons = '';

		foreach (
			[
				'preview' => __( 'Preview', 'arraypress' ),
				'test'    => __( 'Send a test', 'arraypress' ),
			] as $action => $label
		) {
			// A button for a handler nobody registered posts to a 404, which
			// looks exactly like a button that does nothing.
			if ( '' === (string) ( $names[ $action ] ?? '' ) ) {
				continue;
			}

			$button = new Attributes();
			$button->set( 'type', 'button' );
			$button->add_class( 'button', 'field-kit__email-action' );
			$button->set( 'data-action', (string) $names[ $action ] );
			$button->set( 'data-payload-from', $field->input_id() );
			$button->set( 'data-endpoint', rest_url( Runtime::rest_namespace() . '/action' ) );
			$button->set( 'data-nonce', wp_create_nonce( 'wp_rest' ) );
			$button->set( 'data-field', $field->key() );

			$buttons .= sprintf( '<button%s>%s</button>', $button->render(), esc_html( $label ) );
		}

		if ( '' === $buttons ) {
			return '';
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
		return $this->sanitize_children( $this->with_parts( $field ), $value );
	}

	/**
	 * The panel heading is the field's name.
	 *
	 * A label or a legend repeating it would announce the email twice.
	 *
	 * @return bool
	 */
	public function is_self_labelling(): bool {
		return true;
	}

	/**
	 * Not a fieldset: the panel is a labelled region already.
	 *
	 * @return bool
	 */
	public function is_grouped(): bool {
		return false;
	}

	/**
	 * A panel, not a control beside a label.
	 *
	 * @return bool
	 */
	public function spans_row(): bool {
		return true;
	}
}
