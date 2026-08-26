<?php
/**
 * Select Field Type
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Attributes;
use ArrayPress\FieldKit\Field;

/**
 * A single-choice dropdown.
 */
class SelectType extends AbstractType {

	/**
	 * Render the select.
	 *
	 * @param Field      $field      The field.
	 * @param Attributes $attributes Prepared attributes.
	 *
	 * @return string
	 */
	public function render( Field $field, Attributes $attributes ): string {
		$attributes->add_class( 'field-kit__select' );

		if ( $this->is_multiple( $field ) ) {
			$attributes->set( 'multiple', true );
			// A multiple select posts an array, so the name must say so.
			$attributes->set( 'name', $attributes->get( 'name' ) . '[]' );
		}

		if ( $this->uses_enhanced_ui( $field ) ) {
			$attributes->add_class( 'field-kit__select--enhanced' );

			$placeholder = '' !== $field->placeholder()
				? $field->placeholder()
				: $this->default_placeholder( $field );

			$attributes->set_if( '' !== $placeholder, 'data-placeholder', $placeholder );

			// A control allowed to invent values is a tag input. Same
			// combobox, one permission.
			$attributes->set_if( (bool) $field->get( 'creatable', false ), 'data-creatable', 'true' );
		}

		return sprintf(
			'<select%s>%s</select>',
			$attributes->render(),
			$this->render_options( $field )
		);
	}

	/**
	 * Render the option list.
	 *
	 * @param Field $field The field.
	 *
	 * @return string
	 */
	protected function render_options( Field $field ): string {
		$selected = $this->selected_values( $field );
		$markup   = '';

		// A non-required single select needs an empty option, or the first
		// real option is silently pre-selected and cannot be unset.
		if ( ! $this->is_multiple( $field ) && ! $field->is_required() ) {
			$markup .= sprintf(
				'<option value="">%s</option>',
				esc_html( $field->get( 'empty_label', $field->placeholder() ?: __( '— Select —', 'arraypress' ) ) )
			);
		}

		foreach ( $field->options() as $value => $label ) {
			// An option group is a nested array of options.
			if ( is_array( $label ) ) {
				$group = '';

				foreach ( $label as $sub_value => $sub_label ) {
					$group .= $this->render_option( (string) $sub_value, (string) $sub_label, $selected );
				}

				$markup .= sprintf( '<optgroup label="%s">%s</optgroup>', esc_attr( (string) $value ), $group );
				continue;
			}

			$markup .= $this->render_option( (string) $value, (string) $label, $selected );
		}

		return $markup . $this->render_created_options( $field, $selected );
	}

	/**
	 * Options for values that were invented rather than chosen.
	 *
	 * A creatable control stores whatever was typed, and the configured
	 * options will never contain it. Without this the value saved, then had
	 * no `<option>` to be selected on when the page was drawn again — so it
	 * vanished from the control, looked like it had never saved, and was
	 * genuinely lost on the next save, because a value with no option is not
	 * in the form that submits.
	 *
	 * The relational types have always done this, resolving labels for what
	 * is stored. That is why a creatable `ajax` field kept its words and a
	 * creatable `enhanced_select` did not.
	 *
	 * @param Field    $field    The field.
	 * @param string[] $selected Currently selected values.
	 *
	 * @return string
	 */
	private function render_created_options( Field $field, array $selected ): string {
		if ( ! (bool) $field->get( 'creatable', false ) ) {
			return '';
		}

		$known = [];

		foreach ( $field->options() as $value => $label ) {
			if ( is_array( $label ) ) {
				$known = array_merge( $known, array_map( 'strval', array_keys( $label ) ) );

				continue;
			}

			$known[] = (string) $value;
		}

		$markup = '';

		foreach ( array_diff( $selected, $known ) as $value ) {
			// Nothing selected comes back as a single empty string rather
			// than an empty list, and rendering an option for it gives a
			// multiple control one blank chip with a remove button and no
			// label — on every empty creatable field on the page.
			if ( '' === $value ) {
				continue;
			}

			// The value is its own label: nobody chose it from a list, so
			// there is nothing else to call it.
			$markup .= $this->render_option( $value, $value, $selected );
		}

		return $markup;
	}

	/**
	 * Render one option.
	 *
	 * @param string   $value    Option value.
	 * @param string   $label    Option label.
	 * @param string[] $selected Currently selected values.
	 *
	 * @return string
	 */
	protected function render_option( string $value, string $label, array $selected ): string {
		return sprintf(
			'<option value="%s"%s%s>%s</option>',
			esc_attr( $value ),
			// Loose comparison on purpose: an option key of 1 and a stored
			// value of "1" are the same choice.
			in_array( $value, $selected, false ) ? ' selected' : '', // phpcs:ignore WordPress.PHP.StrictInArray.FoundNonStrictFalse -- see comment.
			$this->option_attributes( $value ),
			esc_html( $label )
		);
	}

	/**
	 * Extra attributes for one option.
	 *
	 * Nothing by default. A subtype that has something to say about each
	 * choice puts it here, where it reaches both the native select and the
	 * combobox built over it -- the combobox reads its rows back off the
	 * real options, so anything hung on them is available to it without a
	 * second source of truth.
	 *
	 * @param string $value Option value.
	 *
	 * @return string Attribute markup, already escaped, with a leading space.
	 */
	protected function option_attributes( string $value ): string {
		return '';
	}

	/**
	 * The currently selected values, always as a list.
	 *
	 * @param Field $field The field.
	 *
	 * @return string[]
	 */
	protected function selected_values( Field $field ): array {
		$value = $field->value();

		return array_map( 'strval', is_array( $value ) ? $value : [ $value ] );
	}

	/**
	 * Whether the control accepts more than one value.
	 *
	 * @param Field $field The field.
	 *
	 * @return bool
	 */
	protected function is_multiple( Field $field ): bool {
		return (bool) $field->get( 'multiple', false );
	}

	/**
	 * What the combobox says when nothing is chosen and none was configured.
	 *
	 * A single select has an empty option to fall back on — the combobox
	 * borrows its label, which is what a native select shows. A multiple
	 * select has no empty option at all, so without this the control is an
	 * empty box with no indication that it can be typed in, and a creatable
	 * one gives no hint that typing a value it does not know is allowed.
	 *
	 * @param Field $field The field.
	 *
	 * @return string
	 */
	protected function default_placeholder( Field $field ): string {
		if ( (bool) $field->get( 'creatable', false ) ) {
			return __( 'Choose, or type to add', 'arraypress' );
		}

		return $this->is_multiple( $field )
			? __( 'Choose one or more', 'arraypress' )
			: '';
	}

	/**
	 * Whether to upgrade the control with the searchable UI.
	 *
	 * @param Field $field The field.
	 *
	 * @return bool
	 */
	protected function uses_enhanced_ui( Field $field ): bool {
		return (bool) $field->get( 'searchable', false );
	}

	/**
	 * Coerce a submitted value.
	 *
	 * A value outside the configured options is discarded rather than
	 * stored: the option list is the allow-list.
	 *
	 * @param mixed $value Raw submitted value.
	 * @param Field $field The field.
	 *
	 * @return mixed
	 */
	public function sanitize( mixed $value, Field $field ): mixed {
		$allowed = $this->allowed_values( $field );

		if ( $this->is_multiple( $field ) ) {
			$values = array_map( 'strval', (array) $value );

			return [] === $allowed
				? array_map( 'sanitize_text_field', $values )
				: array_values( array_intersect( $values, $allowed ) );
		}

		$value = sanitize_text_field( (string) ( is_array( $value ) ? '' : $value ) );

		if ( [] === $allowed ) {
			return $value;
		}

		return in_array( $value, $allowed, true ) ? $value : '';
	}

	/**
	 * Flatten the option list into permitted values.
	 *
	 * Returns an empty list when options are supplied dynamically, since
	 * there is nothing to check against at save time.
	 *
	 * @param Field $field The field.
	 *
	 * @return string[]
	 */
	protected function allowed_values( Field $field ): array {
		// A creatable control has no allow-list by definition — the point of
		// it is that a value need not be one of the options. Returning the
		// options would silently discard every value anyone created.
		if ( (bool) $field->get( 'creatable', false ) ) {
			return [];
		}

		$allowed = [];

		foreach ( $field->options() as $value => $label ) {
			if ( is_array( $label ) ) {
				foreach ( array_keys( $label ) as $sub_value ) {
					$allowed[] = (string) $sub_value;
				}
				continue;
			}

			$allowed[] = (string) $value;
		}

		return $allowed;
	}

	/**
	 * The placeholder becomes the empty option's label.
	 *
	 * @return bool
	 */
	public function supports_placeholder(): bool {
		return true;
	}

	/**
	 * A chosen value, or a list of them when the control takes several.
	 *
	 * The options are the allow-list, so they become the schema's enum —
	 * except where the field may invent values, which is the one case where
	 * the options are not the whole story.
	 *
	 * @param Field $field The field.
	 *
	 * @return array<string, mixed>
	 */
	public function schema( Field $field ): array {
		$one = [ 'type' => 'string' ];

		$allowed = $this->allowed_values( $field );

		if ( [] !== $allowed ) {
			// The empty option is a real choice: it is how a non-required
			// select says nothing was picked.
			$one['enum'] = $field->is_required() ? $allowed : array_merge( [ '' ], $allowed );
		}

		return $this->is_multiple( $field )
			? [
				'type'  => 'array',
				'items' => $one,
			]
			: $one;
	}

	/**
	 * Fits an inline row.
	 *
	 * A dropdown fits a row, searchable or not — and assigning something from a list is most of what quick edit is for.
	 *
	 * @return bool
	 */
	public function supports_inline(): bool {
		return true;
	}

	/**
	 * The configuration keys this type reads.
	 *
	 * @return string[]
	 */
	public function config_keys(): array {
		return array_merge(
			parent::config_keys(),
			[ 'creatable', 'empty_label', 'multiple', 'searchable' ]
		);
	}
}
