<?php
/**
 * Currency Field Type
 *
 * @package     ArrayPress\FieldKit
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.2.0
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

/**
 * Choose a currency, stored as its ISO 4217 code.
 *
 * A select over the `currencies` preset, which is every currency wp-money
 * knows, labelled "GBP - British Pound (£)" so the list can be searched by
 * code, by name or by symbol.
 *
 * wp-money is suggested rather than required. A store that supports three
 * currencies has no use for a list of a hundred and thirty-six, and passes
 * its own `options` map instead -- which works without the package, since
 * the option list is the allow-list and nothing else is consulted.
 */
final class CurrencyType extends AbstractCodeType {

	/**
	 * Options come from the currencies preset.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return array_merge(
			parent::defaults(),
			[
				'options'     => 'currencies',
				'placeholder' => __( 'Select a currency', 'arraypress' ),
			]
		);
	}
}
