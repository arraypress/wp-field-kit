<?php
/**
 * User Search Source
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Search;

use WP_User_Query;

/**
 * Searches users.
 *
 * Requires `list_users`, not the blanket `edit_posts` the other sources use:
 * an author can edit posts and has no business enumerating every account and
 * email address on the site.
 */
final class UserSource implements Source {

	/**
	 * The name a field refers to this source by.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'user';
	}

	/**
	 * The capability required to search it.
	 *
	 * @return string
	 */
	public function capability(): string {
		return 'list_users';
	}

	/**
	 * Search users.
	 *
	 * @param string               $term  Search term.
	 * @param array<string, mixed> $args  Arguments the field supplied.
	 * @param int                  $page  One-based page number.
	 * @param int                  $limit Results per page.
	 *
	 * @return array{results: array<int, array{id: string, text: string}>, more: bool}
	 */
	public function search( string $term, array $args, int $page, int $limit ): array {
		$query_args = [
			'search'         => '' === $term ? '' : '*' . $term . '*',
			'search_columns' => [ 'user_login', 'user_email', 'display_name', 'user_nicename' ],
			'number'         => $limit,
			'paged'          => $page,
			'fields'         => [ 'ID', 'display_name', 'user_email' ],
			'count_total'    => true,
		];

		if ( isset( $args['role'] ) && is_string( $args['role'] ) ) {
			$roles = wp_roles()->get_names();

			if ( isset( $roles[ $args['role'] ] ) ) {
				$query_args['role'] = $args['role'];
			}
		}

		$query   = new WP_User_Query( $query_args );
		$results = [];

		foreach ( $query->get_results() as $user ) {
			$results[] = [
				'id'   => (string) $user->ID,
				'text' => sprintf( '%s (%s)', $user->display_name, $user->user_email ),
			];
		}

		return [
			'results' => $results,
			'more'    => ( $page * $limit ) < (int) $query->get_total(),
		];
	}
}
