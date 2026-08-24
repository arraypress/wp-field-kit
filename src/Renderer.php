<?php
/**
 * Field Renderer
 *
 * @package     ArrayPress\FieldKit
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit;

use ArrayPress\FieldKit\Support\Badge;
use ArrayPress\FieldKit\Support\Markup;

/**
 * Wraps a control in its label, description and accessibility associations.
 *
 * Every type renders the control and nothing else. Everything a screen reader
 * needs is added here, once, so accessibility is a property of the kit rather
 * than something each of fifty-odd types has to remember:
 *
 * - a single control gets `<label for>`; a group of controls gets
 *   `<fieldset>` + `<legend>`, because there is no one element for a label to
 *   point at.
 * - a description is given an id and referenced by `aria-describedby`, so it
 *   is announced with the control instead of being orphaned text.
 * - `required` is mirrored to `aria-required`, and an error sets
 *   `aria-invalid` and announces through `role="alert"`.
 * - a self-labelling control (a checkbox with its text beside the box) is not
 *   given a second label above it.
 */
final class Renderer {

	/**
	 * Render one field, wrapper and all.
	 *
	 * @param Field  $field      The normalized field.
	 * @param string $error      Optional validation message.
	 * @param bool   $with_label Whether this renderer supplies the visible
	 *                           heading. False means the caller has drawn one
	 *                           itself — a term screen's table header cell,
	 *                           say — so a second visible heading here would
	 *                           read as a duplicate.
	 *
	 * @return string
	 */
	public function render( Field $field, string $error = '', bool $with_label = true ): string {
		$type = $field->type();

		if ( ! $type->stores_value() && ! $field->has( 'label' ) ) {
			// Layout types with nothing to label render bare.
			return $type->render( $field, $this->control_attributes( $field, $error ) );
		}

		$attributes = $this->control_attributes( $field, $error );
		$control    = $type->render( $field, $attributes );
		$describers = $this->describer_markup( $field, $error );

		if ( $type->is_grouped() ) {
			return $this->wrap_fieldset( $field, $control, $describers, $error, $with_label );
		}

		return $this->wrap_labelled( $field, $control, $describers, $with_label );
	}

	/**
	 * Build the attributes a control must carry.
	 *
	 * @param Field  $field The field.
	 * @param string $error Validation message, if any.
	 *
	 * @return Attributes
	 */
	public function control_attributes( Field $field, string $error = '' ): Attributes {
		$attributes = new Attributes();
		$type       = $field->type();

		$attributes->set( 'id', $field->input_id() );
		$attributes->set( 'name', $field->input_name() );

		// A grouped control is described by its legend, so the individual
		// inputs inside it must not each repeat the association.
		if ( ! $type->is_grouped() ) {
			$described = $this->describedby_ids( $field, $error );

			$attributes->set_if( [] !== $described, 'aria-describedby', implode( ' ', $described ) );
		}

		$attributes->set_if( $field->is_required(), 'required', true );
		$attributes->set_if( $field->is_required(), 'aria-required', 'true' );
		$attributes->set_if( '' !== $error, 'aria-invalid', 'true' );

		$attributes->set_if(
			$type->supports_placeholder() && '' !== $field->placeholder(),
			'placeholder',
			$field->placeholder()
		);

		// A showing badge locks the control. Rendering the field but leaving it
		// settable would let someone configure a feature the install will not
		// honour, and then wonder why nothing happens.
		$attributes->set_if(
			(bool) $field->get( 'disabled' ) || Badge::locks( $field ),
			'disabled',
			true
		);
		$attributes->set_if( (bool) $field->get( 'readonly' ), 'readonly', true );
		$attributes->set_if( $field->has( 'class' ), 'class', $field->get( 'class' ) );
		$attributes->set_if( $field->has( 'autocomplete' ), 'autocomplete', $field->get( 'autocomplete' ) );

		foreach ( (array) $field->get( 'data', [] ) as $key => $value ) {
			$attributes->set( 'data-' . $key, $value );
		}

		return $attributes;
	}

	/**
	 * Ids the control should point `aria-describedby` at.
	 *
	 * @param Field  $field The field.
	 * @param string $error Validation message, if any.
	 *
	 * @return string[]
	 */
	private function describedby_ids( Field $field, string $error ): array {
		$ids = [];

		if ( '' !== $field->description() ) {
			$ids[] = $field->input_id() . '__description';
		}

		if ( '' !== $error ) {
			$ids[] = $field->input_id() . '__error';
		}

		return $ids;
	}

	/**
	 * The description and error markup a control refers to.
	 *
	 * @param Field  $field The field.
	 * @param string $error Validation message, if any.
	 *
	 * @return string
	 */
	private function describer_markup( Field $field, string $error ): string {
		$markup = '';

		if ( '' !== $field->description() ) {
			// core's .description, so help text matches every other admin
			// screen and follows the user's colour scheme without restyling.
			$markup .= sprintf(
				'<p class="description" id="%s">%s</p>',
				esc_attr( $field->input_id() . '__description' ),
				wp_kses_post( $field->description() )
			);
		}

		if ( '' !== $error ) {
			// role="alert" so the message is announced when it appears rather
			// than only being found by someone already navigating the field.
			// core's inline notice markup rather than a bespoke error style.
			$markup .= sprintf(
				'<div class="notice notice-error inline" id="%s" role="alert"><p>%s</p></div>',
				esc_attr( $field->input_id() . '__error' ),
				esc_html( $error )
			);
		}

		return $markup;
	}

	/**
	 * Wrap a single control in its label.
	 *
	 * @param Field  $field      The field.
	 * @param string $control    Rendered control markup.
	 * @param string $describers Description and error markup.
	 *
	 * @return string
	 */
	private function wrap_labelled( Field $field, string $control, string $describers, bool $with_label = true ): string {
		$label = '';

		// A caller drawing its own heading — a table row's header cell — owns
		// the badge too, and gets it from Badge::for_field(). Rendering one
		// here as well would put a pill against the side of the control,
		// which is where it looked wrong enough to be reported.
		$badge = $with_label ? Badge::for_field( $field ) : '';

		// A self-labelling control already carries its text; a second label
		// above it would announce the field twice.
		if ( $with_label && ! $field->type()->is_self_labelling() && '' !== $field->label() ) {
			$label = sprintf(
				'<label for="%s">%s%s%s</label>',
				esc_attr( $field->input_id() ),
				esc_html( $field->label() ),
				$this->required_marker( $field ),
				$badge
			);

			$badge = '';
		}

		// A self-labelling control puts its own text inside its own label, so
		// there is nothing to append the badge to. It follows the control
		// rather than preceding it — before the checkbox it reads as a label
		// for the box.
		return $this->wrapper( $field, $label . $control . $badge . $describers );
	}

	/**
	 * Wrap a group of controls in a fieldset.
	 *
	 * @param Field  $field      The field.
	 * @param string $control    Rendered control markup.
	 * @param string $describers Description and error markup.
	 *
	 * @return string
	 */
	private function wrap_fieldset( Field $field, string $control, string $describers, string $error = '', bool $with_label = true ): string {
		// The legend is never dropped, only hidden. A fieldset without one is
		// announced as an unnamed group, so a caller drawing its own visible
		// heading still needs this to exist for assistive technology — it is
		// simply not shown twice.
		// As above: a caller drawing its own heading owns the badge. A hidden
		// legend would hide it anyway, which for a badge is the same as
		// leaving it out.
		$badge     = $with_label ? Badge::for_field( $field ) : '';
		$in_legend = $badge;

		$legend = '' === $field->label()
			? ''
			: sprintf(
				// The inner span is core's own shape — options-general.php and
				// options-discussion.php both write it this way — and exists
				// because a legend cannot be positioned reliably on its own.
				'<legend class="field-kit__legend%s"><span>%s%s%s</span></legend>',
				$with_label ? '' : ' screen-reader-text',
				esc_html( $field->label() ),
				$this->required_marker( $field ),
				$in_legend
			);

		$badge = '' === $in_legend ? $badge : '';

		$fieldset = new Attributes();
		$fieldset->add_class( 'field-kit__fieldset' );
		$fieldset->set_if( $field->is_required(), 'aria-required', 'true' );

		// The group is described as a whole; the individual inputs inside it
		// deliberately do not each repeat the association.
		$described = $this->describedby_ids( $field, $error );
		$fieldset->set_if( [] !== $described, 'aria-describedby', implode( ' ', $described ) );

		return $this->wrapper(
			$field,
			sprintf( '<fieldset%s>%s%s%s%s</fieldset>', $fieldset->render(), $legend, $badge, $control, $describers )
		);
	}

	/**
	 * The visual and announced marker for a required field.
	 *
	 * The asterisk is decorative — `aria-required` on the control is what
	 * conveys the state — so it is hidden from assistive technology and the
	 * word is provided for it instead.
	 *
	 * @param Field $field The field.
	 *
	 * @return string
	 */
	private function required_marker( Field $field ): string {
		return Markup::required_marker( $field->is_required() );
	}

	/**
	 * The outer wrapper, carrying conditional-logic state.
	 *
	 * @param Field  $field The field.
	 * @param string $inner Inner markup.
	 *
	 * @return string
	 */
	private function wrapper( Field $field, string $inner ): string {
		$attributes = new Attributes();

		$attributes->add_class( 'field-kit__field', 'field-kit__field--' . $field->type()->id() );
		$attributes->set( 'data-field-key', $field->key() );

		if ( Badge::locks( $field ) ) {
			$attributes->add_class( 'field-kit__field--locked' );
		}

		$conditions = Conditions::from( $field->get( 'show_when', $field->get( 'depends', [] ) ) );

		if ( ! $conditions->is_empty() ) {
			// Set as an array; Attributes JSON-encodes and escapes it once,
			// which is the whole reason it is an object and not a string.
			$attributes->set( 'data-conditions', $conditions->to_array() );
			$attributes->add_class( 'field-kit__field--conditional' );
		}

		return sprintf( '<div%s>%s</div>', $attributes->render(), $inner );
	}
}
