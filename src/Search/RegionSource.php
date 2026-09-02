<?php
/**
 * Region Search Source
 *
 * @package     ArrayPress\FieldKit
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.2.0
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Search;

use ArrayPress\Countries\Countries;
use ArrayPress\Countries\Regions;

/**
 * The states, provinces and counties of one country.
 *
 * Built for the region field, which cannot know its country until the page
 * is drawn and the country beside it has been read. The script asks here
 * with the code it found and gets the whole list back. Not a search in the
 * post source's sense -- there is no query behind it and nothing to page
 * through -- but the one endpoint every searchable field already talks to
 * is the right place for it: a second route would be a second nonce, a
 * second capability check and a second thing for a prefixed build to keep
 * distinct.
 *
 * The whole list every time, whatever the limit. A select needs all of its
 * options or it cannot show what is stored, and the longest list
 * wp-countries holds is a few dozen. Paging a list that fits on one screen
 * would only hand the script a `more` it has no way to act on.
 */
final class RegionSource implements Source {

	/**
	 * The name a field refers to this source by.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'region';
	}

	/**
	 * The capability required to search it.
	 *
	 * Public data -- the same list is printed on every checkout form on the
	 * web -- so `read`, which every logged-in user has. The endpoint still
	 * refuses an anonymous request before it gets this far.
	 *
	 * @return string
	 */
	public function capability(): string {
		return 'read';
	}

	/**
	 * Every region of the requested country, filtered by the term.
	 *
	 * The term is matched as a substring of the name or the code, in either
	 * case, so "ny" finds New York by its code and "york" by its name. Page
	 * and limit are accepted because the contract has them, and ignored for
	 * the reason in the class comment.
	 *
	 * @param string               $term  Search term.
	 * @param array<string, mixed> $args  Arguments the field supplied; `country` is the one read.
	 * @param int                  $page  One-based page number. Ignored.
	 * @param int                  $limit Results per page. Ignored.
	 *
	 * @return array{results: array<int, array{id: string, text: string}>, more: bool}
	 */
	public function search( string $term, array $args, int $page, int $limit ): array {
		$term    = trim( $term );
		$results = [];

		foreach ( $this->regions( $args ) as $code => $name ) {
			// Cast, because PHP has already turned a numeric code into an
			// integer key by the time it comes out of the table.
			$code = (string) $code;
			$name = (string) $name;

			if ( '' !== $term && false === mb_stripos( $name, $term ) && false === mb_stripos( $code, $term ) ) {
				continue;
			}

			$results[] = [
				'id'   => $code,
				'text' => $name,
			];
		}

		return [
			'results' => $results,
			'more'    => false,
		];
	}

	/**
	 * The regions of the country the request named.
	 *
	 * Nothing for a country that is not one, and nothing for one with no
	 * subdivisions on file: to the field the two are the same answer, since
	 * either way there is no list to choose from. Regions::for() sanitizes
	 * the code itself, but it is done here as well so that what arrives
	 * from the page is refused in the open rather than by accident.
	 *
	 * @param array<string, mixed> $args Arguments the field supplied.
	 *
	 * @return array<string, string>
	 */
	private function regions( array $args ): array {
		$country = $args['country'] ?? '';

		if ( ! is_scalar( $country ) || ! class_exists( Regions::class ) ) {
			return [];
		}

		$country = Countries::sanitize( (string) $country );

		return null === $country ? [] : Regions::for( $country );
	}
}
