<?php
/**
 * Country Field Type
 *
 * @package     ArrayPress\FieldKit
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.2.0
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\Countries\Countries;
use ArrayPress\FieldKit\Field;

/**
 * Choose a country, stored as its ISO 3166-1 alpha-2 code.
 *
 * A select over the `countries` preset, which is every country wp-countries
 * knows. Set `continents => true` to group the list by continent, which a
 * shipping-zone or tax field wants and a billing address does not.
 *
 * The stored value is the two-letter code, whatever the option list was
 * labelled: a consumer that passes its own `options` map still gets codes
 * back, and one keyed by something that is not a country code gets nothing,
 * because a country field that stores "UK" is a country field every other
 * library will misread.
 */
final class CountryType extends AbstractCodeType {

	/**
	 * Options come from the countries preset.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return array_merge(
			parent::defaults(),
			[
				'options'     => 'countries',
				'placeholder' => __( 'Select a country', 'arraypress' ),
				'continents'  => false,
			]
		);
	}

	/**
	 * Coerce a submitted value.
	 *
	 * The option list already bounds it. wp-countries is asked as well,
	 * where it is here, so that a custom option list cannot let a value
	 * through that is not a country code -- see the class comment.
	 *
	 * @param mixed $value Raw submitted value.
	 * @param Field $field The field.
	 *
	 * @return mixed
	 */
	public function sanitize( mixed $value, Field $field ): mixed {
		$value = parent::sanitize( $value, $field );

		if ( ! class_exists( Countries::class ) ) {
			return $value;
		}

		if ( is_array( $value ) ) {
			return array_values(
				array_filter(
					array_map( static fn( string $code ): string => (string) Countries::sanitize( $code ), $value ),
					static fn( string $code ): bool => '' !== $code
				)
			);
		}

		return (string) Countries::sanitize( (string) $value );
	}

	/**
	 * The configuration keys this type reads.
	 *
	 * `continents` is read by the countries preset, on this type's behalf:
	 * it is the field's setting, and the preset is handed the field.
	 *
	 * @return string[]
	 */
	public function config_keys(): array {
		return array_merge( parent::config_keys(), [ 'continents' ] );
	}
}
