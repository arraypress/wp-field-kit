<?php
/**
 * Post Search Source
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Search;

use WP_Query;

/**
 * Searches posts of a requested type.
 *
 * The requested post type is checked against the ones actually registered
 * and shown in the admin, rather than passed to WP_Query as given: the type
 * arrives from the page, so treating it as trusted would let a request
 * enumerate any post type on the site, including private ones.
 */
final class PostSource implements Source {

	/**
	 * The name a field refers to this source by.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'post';
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
	 * Search post titles.
	 *
	 * @param string               $term  Search term.
	 * @param array<string, mixed> $args  Arguments the field supplied.
	 * @param int                  $page  One-based page number.
	 * @param int                  $limit Results per page.
	 *
	 * @return array{results: array<int, array{id: string, text: string}>, more: bool}
	 */
	public function search( string $term, array $args, int $page, int $limit ): array {
		$post_types = $this->post_type( $args );

		if ( [] === $post_types ) {
			return [
				'results' => [],
				'more'    => false,
			];
		}

		$query = new WP_Query(
			[
				'post_type'              => $post_types,
				'post_status'            => [ 'publish', 'draft', 'pending', 'private' ],
				// Unpublished work is listed for whoever may edit it, which
				// is what `perm` decides. Without it an explicit status list
				// shows every author's drafts to anyone holding edit_posts.
				'perm'                   => 'editable',
				's'                      => $term,
				'posts_per_page'         => $limit,
				'paged'                  => $page,
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);

		$results = [];

		foreach ( $query->posts as $post ) {
			$title = get_the_title( $post );

			$results[] = [
				'id'   => (string) $post->ID,
				/* translators: %d: post ID */
				'text' => '' === $title ? sprintf( __( '(no title) #%d', 'arraypress' ), $post->ID ) : $title,
			];
		}

		return [
			'results' => $results,
			'more'    => $page < (int) $query->max_num_pages,
		];
	}

	/**
	 * The post types to query: those asked for that exist, and that the
	 * current user may edit.
	 *
	 * The route's capability is a blanket edit_posts, because a picker has
	 * to be reachable to be useful. A post type with a capability map of its
	 * own -- an order, a subscription -- is a different question, and one its
	 * own edit_posts answers. Nothing is substituted for a request naming
	 * none the user may see: an empty list is an empty result, not a search
	 * of `post` instead.
	 *
	 * @param array<string, mixed> $args Arguments the field supplied.
	 *
	 * @return string[]
	 */
	private function post_type( array $args ): array {
		$requested = $args['post_type'] ?? 'post';
		$requested = is_array( $requested ) ? $requested : [ (string) $requested ];

		$allowed = get_post_types( [ 'show_ui' => true ], 'names' );
		$allowed = array_intersect( $requested, $allowed );

		return array_values( array_filter( $allowed, [ $this, 'can_edit' ] ) );
	}

	/**
	 * Whether the current user may edit a post type at all.
	 *
	 * @param string $post_type The post type.
	 *
	 * @return bool
	 */
	private function can_edit( string $post_type ): bool {
		$object = get_post_type_object( $post_type );

		return null !== $object && current_user_can( $object->cap->edit_posts );
	}
}
