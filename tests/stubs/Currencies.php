<?php
/**
 * A stand-in for wp-money's Currencies.
 *
 * wp-money is suggested, not required, so the suite cannot count on it being
 * installed -- and the currency type has to work without it, which is the
 * point of testing against a stand-in. A handful of currencies is enough to
 * prove the shape; the real table has a hundred and thirty-six.
 *
 * Its own file rather than a block in stubs.php, because a namespaced class
 * cannot be declared in a file of global functions. stubs.php requires it,
 * guarded, so a library that builds on the kit still gets the whole set from
 * one require.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\Money;

/**
 * The public surface the kit uses, shaped like the real one.
 */
final class Currencies {

	/**
	 * Keyed by lowercase ISO-4217 code, as wp-money keeps them.
	 *
	 * @var array<string, array{name: string, symbol: string}>
	 */
	private const CURRENCIES = [
		'eur' => [ 'name' => 'Euro', 'symbol' => '€' ],
		'gbp' => [ 'name' => 'British Pound', 'symbol' => '£' ],
		'jpy' => [ 'name' => 'Japanese Yen', 'symbol' => '¥' ],
		'usd' => [ 'name' => 'US Dollar', 'symbol' => '$' ],
	];

	/**
	 * Code => label pairs for a select element.
	 *
	 * @return array<string, string> e.g. `USD` => `USD - US Dollar ($)`.
	 */
	public static function options(): array {
		$options = [];

		foreach ( self::CURRENCIES as $code => $meta ) {
			$upper             = strtoupper( $code );
			$options[ $upper ] = $upper . ' - ' . $meta['name'] . ' (' . $meta['symbol'] . ')';
		}

		asort( $options );

		return $options;
	}

	/**
	 * The display symbol, or the code itself for one it does not know.
	 *
	 * @param string $code ISO-4217 code, any case.
	 *
	 * @return string
	 */
	public static function symbol( string $code ): string {
		return self::CURRENCIES[ strtolower( trim( $code ) ) ]['symbol'] ?? strtoupper( trim( $code ) );
	}
}
