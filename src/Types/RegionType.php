<?php
/**
 * Region Field Type
 *
 * @package     ArrayPress\FieldKit
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.2.0
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\Countries\Countries;
use ArrayPress\Countries\Regions;
use ArrayPress\FieldKit\Attributes;
use ArrayPress\FieldKit\Field;
use ArrayPress\FieldKit\Utils\Runtime;

/**
 * A state, province or county, narrowed to a country.
 *
 * What the control is depends on the country. One with known subdivisions
 * gets a select over them, storing the code the way a country field stores
 * its own; one without gets a text input, because "Yorkshire" is a real
 * answer and there is no list to refuse it against.
 *
 * The country is either fixed -- `'country' => 'US'`, for a form that only
 * ever takes American addresses -- or read from a sibling field named by
 * `country_key`, the arrangement money uses for its currency. PHP cannot
 * know a sibling's value at render time, so that form draws both controls,
 * the text input live and the select empty and disabled, and the script
 * keeps whichever fits the country chosen, on load and on every change.
 * Without the script the text input is what submits, so a code typed by
 * hand still saves.
 *
 * A fixed country wins over a sibling. There is nothing for the script to
 * follow when the answer is already known.
 */
final class RegionType extends AbstractType {

	/**
	 * The most a free-text region may hold.
	 *
	 * Room for any name or code wp-countries knows, and no more: a text box
	 * that exists because the country has no list is not a text box for
	 * anything at all.
	 */
	private const MAX_LENGTH = 32;

	/**
	 * Config defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return [
			'country'     => '',
			'country_key' => '',
		];
	}

	/**
	 * Render the control that fits what is known about the country.
	 *
	 * @param Field      $field      The field.
	 * @param Attributes $attributes Prepared attributes.
	 *
	 * @return string
	 */
	public function render( Field $field, Attributes $attributes ): string {
		$country = $this->fixed_country( $field );

		if ( '' !== $country ) {
			$regions = $this->regions( $country );

			return [] === $regions
				? $this->render_text( $field, $attributes )
				: $this->render_select( $field, $attributes, $regions );
		}

		$sibling = (string) $field->get( 'country_key', '' );

		// Nothing to follow, or a locked field, which has nothing to swap: a
		// disabled control is static whatever the country beside it says.
		if ( '' === $sibling || $attributes->has( 'disabled' ) ) {
			return $this->render_text( $field, $attributes );
		}

		return $this->render_pair( $field, $attributes, $sibling );
	}

	/**
	 * A text input, for a country with no list.
	 *
	 * Core's width class for the visuals, as every other text input; the
	 * kit's own classes as the hooks. The limit is on the input as well as
	 * in sanitize(), so the browser says no before the server has to.
	 *
	 * @param Field      $field      The field.
	 * @param Attributes $attributes Attributes the input will carry.
	 *
	 * @return string
	 */
	private function render_text( Field $field, Attributes $attributes ): string {
		$attributes->set( 'type', 'text' );
		$attributes->set( 'value', $this->scalar( $field->value() ) );
		$attributes->set( 'maxlength', self::MAX_LENGTH );
		$attributes->add_class( 'regular-text', 'field-kit__input', 'field-kit__region-text' );

		return sprintf( '<input%s />', $attributes->render() );
	}

	/**
	 * A select over a fixed country's regions.
	 *
	 * @param Field                 $field      The field.
	 * @param Attributes            $attributes Attributes the select will carry.
	 * @param array<string, string> $regions    Code => name.
	 *
	 * @return string
	 */
	private function render_select( Field $field, Attributes $attributes, array $regions ): string {
		$attributes->add_class( 'field-kit__select', 'field-kit__region-select' );

		// The placeholder is the empty option's label, as on any select. The
		// attribute itself means nothing on one.
		$attributes->remove( 'placeholder' );

		return sprintf(
			'<select%s>%s</select>',
			$attributes->render(),
			$this->render_options( $field, $regions )
		);
	}

	/**
	 * Both controls, for a country the script has to read.
	 *
	 * The select takes the field's id, so the `<label for>` the renderer
	 * wrote points at it; the text input takes a derived id and names itself
	 * with `aria-label`. That is chosen over having the script move `for`
	 * because the label is not this type's to move: a repeater draws it in a
	 * table header and a term screen in a row heading, and neither is a
	 * `<label>` at all. An `aria-label` written here is true wherever the
	 * field is drawn and needs no script to stay true. The cost is that
	 * clicking the visible label focuses nothing while the text input is
	 * live, which is the state the field is in only until the script runs.
	 *
	 * Only one may submit, so the select starts disabled as well as hidden:
	 * a hidden control still posts. Everything else the renderer prepared --
	 * the description reference, the required state, an error -- is carried
	 * by both, since only the live one is ever read.
	 *
	 * @param Field      $field      The field.
	 * @param Attributes $attributes Prepared attributes.
	 * @param string     $sibling    Key of the field holding the country.
	 *
	 * @return string
	 */
	private function render_pair( Field $field, Attributes $attributes, string $sibling ): string {
		$wrapper = new Attributes();
		$wrapper->add_class( 'field-kit__region' );
		$wrapper->set( 'data-country-key', $sibling );
		$wrapper->set( 'data-search-endpoint', rest_url( Runtime::rest_namespace() . '/search' ) );
		$wrapper->set( 'data-search-nonce', wp_create_nonce( 'wp_rest' ) );

		$text = clone $attributes;
		$text->set( 'id', $attributes->get( 'id', '' ) . '_text' );
		$text->set_if( ! $text->has( 'aria-label' ) && '' !== $field->label(), 'aria-label', $field->label() );

		$attributes->set( 'disabled', true );
		$attributes->set( 'hidden', true );
		$attributes->set( 'data-placeholder', $this->empty_label( $field ) );
		$attributes->add_class( 'field-kit__select', 'field-kit__region-select' );
		$attributes->remove( 'placeholder' );

		return sprintf(
			'<span%s>%s<select%s></select></span>',
			$wrapper->render(),
			$this->render_text( $field, $text ),
			$attributes->render()
		);
	}

	/**
	 * The option list, with the stored code selected.
	 *
	 * A non-required select needs the empty option, or the first region is
	 * silently pre-selected and cannot be unset.
	 *
	 * @param Field                 $field   The field.
	 * @param array<string, string> $regions Code => name.
	 *
	 * @return string
	 */
	private function render_options( Field $field, array $regions ): string {
		$selected = $this->scalar( $field->value() );
		$markup   = '';

		if ( ! $field->is_required() ) {
			$markup .= sprintf( '<option value="">%s</option>', esc_html( $this->empty_label( $field ) ) );
		}

		foreach ( $regions as $code => $name ) {
			$code = (string) $code;

			$markup .= sprintf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $code ),
				$code === $selected ? ' selected' : '',
				esc_html( (string) $name )
			);
		}

		return $markup;
	}

	/**
	 * What the empty option says.
	 *
	 * The placeholder where one was given, so a select and a text input
	 * configured the same way prompt the same way.
	 *
	 * @param Field $field The field.
	 *
	 * @return string
	 */
	private function empty_label( Field $field ): string {
		return '' !== $field->placeholder() ? $field->placeholder() : __( 'Select a region', 'arraypress' );
	}

	/**
	 * Coerce a submitted value.
	 *
	 * With a fixed country that has regions, the list is the allow-list and
	 * a code outside it is stored as nothing, whatever case it arrived in.
	 *
	 * Otherwise trimmed text of bounded length, and no more than that. The
	 * country is not available here: with `country_key` it lives in another
	 * field's submission, and sanitize() is handed one value at a time. So
	 * a region chosen from the select cannot be checked against the list it
	 * came from, and is kept as the text it is -- which is also what a
	 * country with no list needs.
	 *
	 * @param mixed $value Raw submitted value.
	 * @param Field $field The field.
	 *
	 * @return string
	 */
	public function sanitize( mixed $value, Field $field ): string {
		$value   = sanitize_text_field( $this->scalar( $value ) );
		$country = $this->fixed_country( $field );

		if ( '' !== $country && [] !== $this->regions( $country ) ) {
			return (string) ( Regions::sanitize( $country, $value ) ?? '' );
		}

		return mb_substr( trim( $value ), 0, self::MAX_LENGTH );
	}

	/**
	 * The shape this type stores.
	 *
	 * An enum where the country is fixed and has a list, since the list is
	 * the allow-list; a string everywhere else, for the reason sanitize()
	 * gives.
	 *
	 * @param Field $field The field.
	 *
	 * @return array<string, mixed>
	 */
	public function schema( Field $field ): array {
		$country = $this->fixed_country( $field );
		$regions = '' === $country ? [] : $this->regions( $country );

		if ( [] === $regions ) {
			return [ 'type' => 'string' ];
		}

		$codes = array_map( 'strval', array_keys( $regions ) );

		return [
			'type' => 'string',
			// The empty option is a real choice: it is how a non-required
			// select says nothing was picked.
			'enum' => $field->is_required() ? $codes : array_merge( [ '' ], $codes ),
		];
	}

	/**
	 * The text input takes one, and the select uses it as its empty option.
	 *
	 * @return bool
	 */
	public function supports_placeholder(): bool {
		return true;
	}

	/**
	 * The configured country, as a code, or nothing.
	 *
	 * Sanitized so that a country nobody has heard of is the same as none:
	 * the alternative is a text input that looks like a decision when it
	 * is a typo.
	 *
	 * @param Field $field The field.
	 *
	 * @return string
	 */
	private function fixed_country( Field $field ): string {
		$country = $this->scalar( $field->get( 'country', '' ) );

		if ( '' === $country || ! class_exists( Countries::class ) ) {
			return '';
		}

		return (string) ( Countries::sanitize( $country ) ?? '' );
	}

	/**
	 * A country's regions, or none where wp-countries is not loaded.
	 *
	 * @param string $country Country code.
	 *
	 * @return array<string, string>
	 */
	private function regions( string $country ): array {
		return class_exists( Regions::class ) ? Regions::for( $country ) : [];
	}

	/**
	 * The configuration keys this type reads.
	 *
	 * @return string[]
	 */
	public function config_keys(): array {
		return array_merge(
			parent::config_keys(),
			[ 'country', 'country_key' ]
		);
	}
}
