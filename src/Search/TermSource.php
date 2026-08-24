<?php
/**
 * Term Search Source
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Search;

use WP_Term_Query;

/**
 * Searches terms in a requested taxonomy.
 *
 * The taxonomy is checked against the registered ones for the same reason
 * the post source checks its post type: it arrives from the page.
 */
final class TermSource implements Source {

	/**
	 * The name a field refers to this source by.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'term';
	}

	/**
	 * The capability required to search it.
	 *
	 * @return string
	 */
	public function capability(): string {
		return 'edit_posts';
	}

	/**
	 * Search terms.
	 *
	 * @param string               $term  Search term.
	 * @param array<string, mixed> $args  Arguments the field supplied.
	 * @param int                  $page  One-based page number.
	 * @param int                  $limit Results per page.
	 *
	 * @return array{results: array<int, array{id: string, text: string}>, more: bool}
	 */
	public function search( string $term, array $args, int $page, int $limit ): array {
		$taxonomy = $this->taxonomy( $args );

		$query = new WP_Term_Query(
			[
				'taxonomy'   => $taxonomy,
				'search'     => $term,
				'number'     => $limit,
				'offset'     => ( $page - 1 ) * $limit,
				'hide_empty' => false,
			]
		);

		$results = [];

		foreach ( (array) $query->get_terms() as $found ) {
			if ( ! $found instanceof \WP_Term ) {
				continue;
			}

			$results[] = [
				'id'   => (string) $found->term_id,
				'text' => $found->name,
			];
		}

		$total = (int) wp_count_terms(
			[
				'taxonomy'   => $taxonomy,
				'search'     => $term,
				'hide_empty' => false,
			]
		);

		return [
			'results' => $results,
			'more'    => ( $page * $limit ) < $total,
		];
	}

	/**
	 * The taxonomy to query, restricted to what really exists.
	 *
	 * @param array<string, mixed> $args Arguments the field supplied.
	 *
	 * @return string|string[]
	 */
	private function taxonomy( array $args ): string|array {
		$requested = $args['taxonomy'] ?? 'category';
		$requested = is_array( $requested ) ? $requested : [ (string) $requested ];

		$allowed = get_taxonomies( [ 'show_ui' => true ], 'names' );
		$allowed = array_values( array_intersect( $requested, $allowed ) );

		return [] === $allowed ? 'category' : $allowed;
	}
}
