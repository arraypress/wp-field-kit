<?php
/**
 * Stored value interpretation tests.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Tests;

use ArrayPress\FieldKit\Value;
use PHPUnit\Framework\TestCase;

/**
 * These decide what a stored value means, and the whole reason they are in
 * the kit is that four libraries were about to answer the same questions four
 * times and draw the line in four slightly different places.
 *
 * Most of what is pinned here is the edges, because the edges are where the
 * copies would have disagreed: whether "0" is off, whether an integer id
 * matches a string option, whether zero is a selection.
 */
final class ValueTest extends TestCase {

	/**
	 * Reset the request state the front-end helpers read.
	 */
	protected function setUp(): void {
		$GLOBALS['fk_permalinks']     = [];
		$GLOBALS['fk_is_singular']    = false;
		$GLOBALS['fk_queried_object'] = 0;
	}

	/**
	 * What counts as on.
	 *
	 * @dataProvider onProvider
	 *
	 * @param mixed $value    A stored value.
	 * @param bool  $expected Whether it is on.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'onProvider' )]
	public function test_what_counts_as_on( mixed $value, bool $expected ): void {
		$this->assertSame( $expected, Value::is_on( $value ) );
	}

	/**
	 * Every shape a checkbox value arrives in.
	 *
	 * The string cases are the ones a plain (bool) cast gets wrong: it reads
	 * "0" as false but "false" as true.
	 *
	 * @return array<string, array{0: mixed, 1: bool}>
	 */
	public static function onProvider(): array {
		return [
			'one'            => [ 1, true ],
			'string one'     => [ '1', true ],
			'true'           => [ true, true ],
			'string true'    => [ 'true', true ],
			'yes'            => [ 'yes', true ],
			'on'             => [ 'on', true ],
			'zero'           => [ 0, false ],
			'string zero'    => [ '0', false ],
			'string false'   => [ 'false', false ],
			'empty string'   => [ '', false ],
			'null'           => [ null, false ],
			'false'          => [ false, false ],
			'an unset field' => [ [], false ],
		];
	}

	/**
	 * A list holds an option whichever way the two were typed.
	 *
	 * A checkbox group's options are keyed by string; a list of ids may come
	 * back as integers. A strict comparison against the wrong one is a silent
	 * false, and silently false is how a feature flag stays off.
	 */
	public function test_membership_ignores_how_the_value_was_typed(): void {
		$this->assertTrue( Value::includes( [ 'pool', 'ac' ], 'pool' ) );
		$this->assertTrue( Value::includes( [ 12, 15 ], '12' ) );
		$this->assertTrue( Value::includes( [ '12', '15' ], '12' ) );

		$this->assertFalse( Value::includes( [ 'pool' ], 'ac' ) );
		$this->assertFalse( Value::includes( 'pool', 'pool' ), 'A string is not a list.' );
		$this->assertFalse( Value::includes( null, 'pool' ) );
	}

	/**
	 * A single id, however it was stored.
	 */
	public function test_a_single_id(): void {
		$this->assertSame( 12, Value::id( 12 ) );
		$this->assertSame( 12, Value::id( '12' ) );
		$this->assertSame( 12, Value::id( [ 12, 15 ] ), 'A multiple field yields its first.' );

		$this->assertSame( 0, Value::id( '' ) );
		$this->assertSame( 0, Value::id( null ) );
		$this->assertSame( 0, Value::id( 'not an id' ) );
	}

	/**
	 * A list of ids, from an array or from the string one was stored as.
	 */
	public function test_a_list_of_ids(): void {
		$this->assertSame( [ 1, 2, 3 ], Value::ids( [ 1, 2, 3 ] ) );
		$this->assertSame( [ 1, 2, 3 ], Value::ids( '1,2,3' ) );
		$this->assertSame( [ 1, 2, 3 ], Value::ids( [ '1', '2', '3' ] ) );

		// Zero is not an id. A relational field stores nothing for "not
		// selected", but a value written by hand may hold a 0.
		$this->assertSame( [ 4 ], Value::ids( [ 0, 4, '' ] ) );

		$this->assertSame( [], Value::ids( '' ) );
		$this->assertSame( [], Value::ids( null ) );
	}

	/**
	 * A page field yields the permalink.
	 */
	public function test_a_url_comes_from_the_permalink(): void {
		$GLOBALS['fk_permalinks'] = [ 9 => 'https://example.test/about/' ];

		$this->assertSame( 'https://example.test/about/', Value::url( 9 ) );
	}

	/**
	 * With nothing selected, the fallback — and home as the last resort.
	 *
	 * A template that puts this in an href needs a URL, not an empty string
	 * that renders as a link to the current page.
	 */
	public function test_a_url_falls_back(): void {
		$this->assertSame( 'https://example.test/shop/', Value::url( 0, 'https://example.test/shop/' ) );
		$this->assertSame( 'https://example.test/', Value::url( 0 ) );

		// A post that exists in the field but not any more in the database.
		$this->assertSame( 'https://example.test/', Value::url( 404 ) );
	}

	/**
	 * "Are we on it" is asked of the queried object, not of is_page().
	 *
	 * A field can point at any post type, and is_page() answers false for
	 * every one that is not a page — which is what the settings helper this
	 * replaces got wrong.
	 */
	public function test_whether_the_stored_post_is_the_one_being_viewed(): void {
		$GLOBALS['fk_is_singular']    = true;
		$GLOBALS['fk_queried_object'] = 9;

		$this->assertTrue( Value::is_viewing( 9 ) );
		$this->assertFalse( Value::is_viewing( 10 ) );
		$this->assertFalse( Value::is_viewing( 0 ) );

		// An archive is not any single post.
		$GLOBALS['fk_is_singular'] = false;
		$this->assertFalse( Value::is_viewing( 9 ) );

		unset( $GLOBALS['fk_is_singular'], $GLOBALS['fk_queried_object'] );
	}

	/**
	 * A term field knows it is on its own term's archive.
	 *
	 * `is_singular()` is false there, so a taxonomy field pointing at the
	 * very term on screen used to answer no — the one case it exists for.
	 *
	 * @dataProvider archiveProvider
	 *
	 * @param string $archive Which archive is showing.
	 * @param string $type    The kind the field holds.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'archiveProvider' )]
	public function test_a_term_or_user_field_knows_its_own_archive( string $archive, string $type ): void {
		$GLOBALS['fk_archive']        = $archive;
		$GLOBALS['fk_queried_object'] = 9;

		$this->assertTrue( Value::is_viewing( 9, $type ) );
		$this->assertFalse( Value::is_viewing( 10, $type ) );

		unset( $GLOBALS['fk_archive'], $GLOBALS['fk_queried_object'] );
	}

	/**
	 * The archives, and what a field holding that kind should say.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function archiveProvider(): array {
		return [
			'a category' => [ 'category', 'term' ],
			'a tag'      => [ 'tag', 'term' ],
			'a taxonomy' => [ 'tax', 'term' ],
			'an author'  => [ 'author', 'user' ],
		];
	}

	/**
	 * An id cannot say what kind of thing it is, so the kinds do not mix.
	 *
	 * Post 42, term 42 and user 42 are three different things. A check that
	 * accepted whichever archive happened to be showing would answer true for
	 * a post field on the term archive that shares its id.
	 */
	public function test_the_kinds_do_not_answer_for_each_other(): void {
		$GLOBALS['fk_archive']        = 'category';
		$GLOBALS['fk_queried_object'] = 9;

		$this->assertTrue( Value::is_viewing( 9, 'term' ) );
		$this->assertFalse( Value::is_viewing( 9, 'post' ) );
		$this->assertFalse( Value::is_viewing( 9, 'user' ) );

		unset( $GLOBALS['fk_archive'], $GLOBALS['fk_queried_object'] );
	}

	/**
	 * Post is the default, so the callers that had one keep working.
	 */
	public function test_post_is_the_default_kind(): void {
		$GLOBALS['fk_is_singular']    = true;
		$GLOBALS['fk_queried_object'] = 9;

		$this->assertSame( Value::is_viewing( 9 ), Value::is_viewing( 9, 'post' ) );

		unset( $GLOBALS['fk_is_singular'], $GLOBALS['fk_queried_object'] );
	}

	/**
	 * A tags value, arriving either way.
	 */
	public function test_a_list_of_strings(): void {
		$this->assertSame( [ 'red', 'blue' ], Value::list( 'red, blue' ) );
		$this->assertSame( [ 'red', 'blue' ], Value::list( [ 'red', ' blue ' ] ) );
		$this->assertSame( [ 'red', 'blue' ], Value::list( 'red|blue', '|' ) );

		// An empty entry between separators is not a tag.
		$this->assertSame( [ 'red' ], Value::list( 'red,,  ,' ) );

		$this->assertSame( [], Value::list( '' ) );
		$this->assertSame( [], Value::list( null ) );
	}
}
