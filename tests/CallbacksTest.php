<?php
/**
 * Callback source and action tests.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Tests;

use ArrayPress\FieldKit\Actions\CallbackAction;
use ArrayPress\FieldKit\Search\CallbackSource;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The two places a consumer's callable meets a request.
 *
 * Both are reached over REST by anyone holding the capability, so what the
 * request can put in front of the callable, and what the callable can put
 * back in front of the browser, are both worth pinning.
 */
final class CallbacksTest extends TestCase {

	/**
	 * Only the arguments the field declared reach the callable.
	 *
	 * The request is the browser's to compose. A callback folding its
	 * arguments into a query must not be steerable with keys the field never
	 * mentioned.
	 */
	public function test_only_declared_arguments_reach_the_callable(): void {
		$seen = null;

		$source = new CallbackSource(
			'demo',
			function ( $term, $ids, $args ) use ( &$seen ) {
				$seen = $args;

				return [];
			},
			'edit_posts',
			[ 'exclude' ]
		);

		$source->search( 'q', [ 'exclude' => '12', 'post_status' => 'private', 'meta_key' => 'x' ], 1, 20 );

		$this->assertSame( [ 'exclude' => '12' ], $seen );
	}

	/**
	 * A source that declared nothing passes nothing.
	 */
	public function test_an_undeclared_source_passes_no_arguments(): void {
		$seen = null;

		$source = new CallbackSource(
			'demo',
			function ( $term, $ids, $args ) use ( &$seen ) {
				$seen = $args;

				return [];
			}
		);

		$source->search( 'q', [ 'post_status' => 'private' ], 1, 20 );

		$this->assertSame( [], $seen );
	}

	/**
	 * The endpoint's page and limit apply to a callback source too.
	 */
	public function test_results_are_paged(): void {
		$source = new CallbackSource(
			'demo',
			static fn() => array_map( static fn( int $i ) => [ 'id' => (string) $i, 'text' => "Item $i" ], range( 1, 45 ) )
		);

		$first = $source->search( '', [], 1, 20 );
		$last  = $source->search( '', [], 3, 20 );

		$this->assertCount( 20, $first['results'] );
		$this->assertTrue( $first['more'] );
		$this->assertSame( '1', $first['results'][0]['id'] );

		$this->assertCount( 5, $last['results'] );
		$this->assertFalse( $last['more'] );
		$this->assertSame( '41', $last['results'][0]['id'] );
	}

	/**
	 * What an exception says stays out of the response.
	 *
	 * A database error names tables and an HTTP client quotes what it got
	 * back; neither is for whoever holds the button's capability.
	 */
	public function test_an_exception_message_is_not_sent_to_the_browser(): void {
		$action = new CallbackAction(
			'demo',
			static function () {
				throw new RuntimeException( 'SQLSTATE[42S02]: table wp_secret_ledger' );
			}
		);

		$result = $action->handle( [] );

		$this->assertFalse( $result['success'] );
		$this->assertStringNotContainsString( 'wp_secret_ledger', $result['message'] );
		$this->assertNotSame( '', $result['message'] );
	}
}
