<?php
/**
 * Callback Search Source
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Search;

/**
 * A source backed by a consumer's own callable.
 *
 * The callable never reaches the page. A field names a source, the name is
 * what travels in the request, and it is resolved against a registry
 * populated server-side — so a request can only ever reach a source someone
 * deliberately registered, and cannot name an arbitrary function.
 *
 * The callable is invoked as `( string $term, array $ids, array $args )`.
 * An empty `$term` with a non-empty `$ids` is a request to resolve labels
 * for a known selection rather than to search, because those are usually
 * different queries.
 */
final class CallbackSource implements Source {

	/**
	 * The name a field refers to this source by.
	 *
	 * @var string
	 */
	private string $name;

	/**
	 * The consumer's callable.
	 *
	 * @var callable
	 */
	private $callback;

	/**
	 * The capability required to search it.
	 *
	 * @var string
	 */
	private string $capability;

	/**
	 * Construct.
	 *
	 * @param string   $name       Source name.
	 * @param callable $callback   The consumer's callable.
	 * @param string   $capability Capability required to search it.
	 */
	public function __construct( string $name, callable $callback, string $capability = 'edit_posts' ) {
		$this->name       = $name;
		$this->callback   = $callback;
		$this->capability = $capability;
	}

	/**
	 * The name a field refers to this source by.
	 *
	 * @return string
	 */
	public function name(): string {
		return $this->name;
	}

	/**
	 * The capability required to search it.
	 *
	 * @return string
	 */
	public function capability(): string {
		return $this->capability;
	}

	/**
	 * Search through the callable.
	 *
	 * @param string               $term  Search term.
	 * @param array<string, mixed> $args  Arguments the field supplied.
	 * @param int                  $page  One-based page number.
	 * @param int                  $limit Results per page.
	 *
	 * @return array{results: array<int, array{id: string, text: string}>, more: bool}
	 */
	public function search( string $term, array $args, int $page, int $limit ): array {
		$results = ( $this->callback )( $term, [], $args );

		return [
			'results' => $this->normalize( $results ),
			'more'    => false,
		];
	}

	/**
	 * Coerce whatever the callable returned into the endpoint's shape.
	 *
	 * Both the `[ id, text ]` shape and a plain `value => label` map are
	 * accepted, because both are the obvious thing to return and neither is
	 * worth a support question.
	 *
	 * @param mixed $results Whatever came back.
	 *
	 * @return array<int, array{id: string, text: string}>
	 */
	private function normalize( mixed $results ): array {
		if ( ! is_array( $results ) ) {
			return [];
		}

		$normalized = [];

		foreach ( $results as $key => $result ) {
			if ( is_array( $result ) && isset( $result['id'] ) ) {
				$normalized[] = [
					'id'   => (string) $result['id'],
					'text' => (string) ( $result['text'] ?? $result['id'] ),
				];
				continue;
			}

			if ( is_scalar( $result ) ) {
				$normalized[] = [
					'id'   => (string) $key,
					'text' => (string) $result,
				];
			}
		}

		return $normalized;
	}
}
