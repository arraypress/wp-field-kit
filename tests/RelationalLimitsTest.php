<?php
/**
 * Sorting and selection limits on relational fields.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Tests;

use ArrayPress\FieldKit\Field;
use ArrayPress\FieldKit\Registry;
use ArrayPress\FieldKit\Renderer;
use PHPUnit\Framework\TestCase;

/**
 * Covers the relationship-style options on post, page, user, taxonomy and ajax.
 */
final class RelationalLimitsTest extends TestCase {

	/**
	 * Every relational type, so an option added to one is added to all.
	 *
	 * @return array
	 */
	public static function relational_types(): array {
		return [ [ 'post' ], [ 'page' ], [ 'user' ], [ 'taxonomy' ], [ 'ajax' ] ];
	}

	/**
	 * Render a field of the given type.
	 *
	 * @param string $type   Field type.
	 * @param array  $config Field configuration.
	 *
	 * @return string
	 */
	private function render( string $type, array $config = [] ): string {
		$field = new Field(
			'demo',
			( new Registry() )->get( $type ),
			array_merge( [ 'label' => 'Demo', 'input_name' => 'demo' ], $config )
		);

		return ( new Renderer() )->render( $field );
	}

	/**
	 * Sanitise a value through the given type.
	 *
	 * @param string $type   Field type.
	 * @param mixed  $value  Submitted value.
	 * @param array  $config Field configuration.
	 *
	 * @return mixed
	 */
	private function sanitize( string $type, mixed $value, array $config = [] ) {
		$registry = new Registry();
		$field    = new Field(
			'demo',
			$registry->get( $type ),
			array_merge( [ 'label' => 'Demo', 'input_name' => 'demo' ], $config )
		);

		return $registry->get( $type )->sanitize( $value, $field );
	}

	/**
	 * Multiple selection already worked; this pins it so it cannot regress.
	 *
	 * @return void
	 */
	public function test_multiple_posts_an_array(): void {
		$markup = $this->render( 'post', [ 'multiple' => true ] );

		$this->assertStringContainsString( 'multiple', $markup );
		$this->assertStringContainsString( 'name="demo[]"', $markup );
	}

	/**
	 * sortable is advertised to the control, on every relational type.
	 *
	 *
	 * @param string $type The field type.
	 *
	 * @return void
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'relational_types' )]
	public function test_sortable_is_advertised( string $type ): void {
		$markup = $this->render( $type, [ 'multiple' => true, 'sortable' => true ] );

		$this->assertStringContainsString( 'data-sortable="true"', $markup, $type );
	}

	/**
	 * Order is meaningless for a single choice, so it is not advertised.
	 *
	 * @return void
	 */
	public function test_sortable_is_ignored_without_multiple(): void {
		$markup = $this->render( 'post', [ 'sortable' => true ] );

		$this->assertStringNotContainsString( 'data-sortable', $markup );
	}

	/**
	 * A field that says nothing about order does not advertise it.
	 *
	 * @return void
	 */
	public function test_sortable_is_off_by_default(): void {
		$this->assertStringNotContainsString(
			'data-sortable',
			$this->render( 'post', [ 'multiple' => true ] )
		);
	}

	/**
	 * min and max reach the control.
	 *
	 * @return void
	 */
	public function test_limits_are_advertised(): void {
		$markup = $this->render( 'post', [ 'multiple' => true, 'min' => 1, 'max' => 3 ] );

		$this->assertStringContainsString( 'data-min="1"', $markup );
		$this->assertStringContainsString( 'data-max="3"', $markup );
	}

	/**
	 * A field with no limits says nothing about them.
	 *
	 * @return void
	 */
	public function test_limits_absent_by_default(): void {
		$markup = $this->render( 'post', [ 'multiple' => true ] );

		$this->assertStringNotContainsString( 'data-min', $markup );
		$this->assertStringNotContainsString( 'data-max', $markup );
	}

	/**
	 * max is enforced on save, not only in the browser.
	 *
	 * The control refuses to take more than max, but the value arrives over
	 * HTTP and anything posting directly ignores the control entirely.
	 *
	 * @return void
	 */
	public function test_max_is_enforced_on_save(): void {
		$this->assertSame(
			[ 1, 2, 3 ],
			$this->sanitize( 'post', [ 1, 2, 3, 4, 5 ], [ 'multiple' => true, 'max' => 3 ] )
		);
	}

	/**
	 * Extras are cut from the end, so the first choices survive.
	 *
	 * @return void
	 */
	public function test_max_keeps_the_earliest_choices(): void {
		$this->assertSame(
			[ 10, 20 ],
			$this->sanitize( 'post', [ 10, 20, 30 ], [ 'multiple' => true, 'max' => 2 ] )
		);
	}

	/**
	 * Without a max, nothing is dropped.
	 *
	 * @return void
	 */
	public function test_no_max_keeps_everything(): void {
		$this->assertSame(
			[ 1, 2, 3, 4, 5 ],
			$this->sanitize( 'post', [ 1, 2, 3, 4, 5 ], [ 'multiple' => true ] )
		);
	}

	/**
	 * A creatable field is capped too -- it takes a different path through
	 * sanitize() and would otherwise ignore the limit.
	 *
	 * @return void
	 */
	public function test_max_applies_to_creatable_fields(): void {
		$this->assertSame(
			[ 'alpha', 'beta' ],
			$this->sanitize(
				'post',
				[ 'alpha', 'beta', 'gamma' ],
				[ 'multiple' => true, 'creatable' => true, 'max' => 2 ]
			)
		);
	}

	/**
	 * Submitted order is the stored order, which is what makes sorting stick.
	 *
	 * @return void
	 */
	public function test_submitted_order_is_preserved(): void {
		$this->assertSame(
			[ 30, 10, 20 ],
			$this->sanitize( 'post', [ 30, 10, 20 ], [ 'multiple' => true ] )
		);
	}

	/**
	 * The new keys are declared, so they do not trip the unknown-key warning.
	 *
	 *
	 * @param string $type The field type.
	 *
	 * @return void
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'relational_types' )]
	public function test_new_keys_are_declared( string $type ): void {
		$keys = ( new Registry() )->get( $type )->config_keys();

		foreach ( [ 'sortable', 'min', 'max', 'multiple' ] as $key ) {
			$this->assertContains( $key, $keys, "{$type} does not declare {$key}" );
		}
	}

	/**
	 * config_keys() does not repeat itself.
	 *
	 * creatable was declared by both SelectType and AbstractRelationalType, so
	 * it appeared twice in every relational type's list.
	 *
	 *
	 * @param string $type The field type.
	 *
	 * @return void
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'relational_types' )]
	public function test_config_keys_have_no_duplicates( string $type ): void {
		$keys = ( new Registry() )->get( $type )->config_keys();

		$this->assertSame( array_values( array_unique( $keys ) ), $keys, "{$type} repeats a config key" );
	}

	/**
	 * A callback source can be told something the search term does not carry.
	 *
	 * `search_args` reaches the endpoint, which passes it to the callback as
	 * its third argument. The usual reason is which record the picker is
	 * sitting in, so it can leave that one out of the results -- a product's
	 * "also suggest" offering the product itself is a choice that looks
	 * available and is thrown away on save.
	 *
	 * The attribute existed before this and nothing could fill it.
	 */
	public function test_search_args_reach_the_page(): void {
		$markup = $this->render( 'ajax', [ 'search_args' => [ 'exclude' => 12 ] ] );

		$this->assertStringContainsString( 'data-search-args', $markup );
		$this->assertStringContainsString( '&quot;exclude&quot;:12', $markup );
	}

	/**
	 * A field that declares none gets no attribute at all.
	 */
	public function test_no_search_args_means_no_attribute(): void {
		$this->assertStringNotContainsString( 'data-search-args', $this->render( 'ajax' ) );
	}

	/**
	 * Only scalars, because the endpoint reduces them to scalars anyway.
	 *
	 * A nested array would arrive as the string "Array" and read as a filter
	 * that had been applied.
	 */
	public function test_search_args_drop_anything_that_is_not_scalar(): void {
		$markup = $this->render( 'ajax', [ 'search_args' => [ 'ok' => 1, 'bad' => [ 1, 2 ] ] ] );

		$this->assertStringContainsString( '&quot;ok&quot;:1', $markup );
		$this->assertStringNotContainsString( 'bad', $markup );
	}
}
