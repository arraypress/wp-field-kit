<?php
/**
 * Search Source Contract
 *
 * @package     ArrayPress\FieldKit
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Search;

/**
 * Something a relational field can search.
 *
 * Sources are looked up by name, and the name is the only thing that reaches
 * the page. The predecessor libraries put a `search_callback` in the field
 * config and had to resolve it from the request; a name resolved against a
 * server-side registry means a request can only ever reach a source someone
 * deliberately registered.
 */
interface Source {

	/**
	 * The name a field refers to this source by.
	 *
	 * @return string
	 */
	public function name(): string;

	/**
	 * The capability required to search it.
	 *
	 * A source over users or private posts is not the same risk as one over
	 * published categories, so each says what it needs rather than sharing
	 * one blanket check.
	 *
	 * @return string
	 */
	public function capability(): string;

	/**
	 * Search.
	 *
	 * @param string               $term  Search term, already sanitized.
	 * @param array<string, mixed> $args  Arguments the field supplied.
	 * @param int                  $page  One-based page number.
	 * @param int                  $limit Results per page.
	 *
	 * @return array{results: array<int, array{id: string, text: string}>, more: bool}
	 */
	public function search( string $term, array $args, int $page, int $limit ): array;
}
