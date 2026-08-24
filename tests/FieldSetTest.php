<?php
/**
 * Field set tests.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Tests;

use ArrayPress\FieldKit\Context\OptionContext;
use ArrayPress\FieldKit\Context\PostMetaContext;
use ArrayPress\FieldKit\FieldSet;
use PHPUnit\Framework\TestCase;

/**
 * The save path, which is where a field library actually loses data.
 */
final class FieldSetTest extends TestCase {

	/**
	 * Reset the stubbed stores between tests.
	 */
	protected function setUp(): void {
		$GLOBALS['fk_meta']    = [];
		$GLOBALS['fk_options'] = [];
	}

	/**
	 * A set over one option.
	 *
	 * @param array<string, array<string, mixed>> $configs Field configuration.
	 *
	 * @return array{0: FieldSet, 1: OptionContext}
	 */
	private function option_set( array $configs ): array {
		$context = new OptionContext( 'fk_test' );

		return [ new FieldSet( $configs, $context, 'fk_test' ), $context ];
	}

	/**
	 * Values round-trip through storage.
	 */
	public function test_values_round_trip(): void {
		[ $set, $context ] = $this->option_set(
			[
				'name'  => [ 'type' => 'text', 'label' => 'Name' ],
				'count' => [ 'type' => 'number', 'label' => 'Count', 'min' => 0, 'max' => 10 ],
			]
		);

		$set->save( [ 'name' => 'Widget', 'count' => '4' ] );

		$this->assertSame( 'Widget', $context->values()['name'] );
		$this->assertSame( 4, $context->values()['count'] );
	}

	/**
	 * The option is written once, not once per field.
	 */
	public function test_option_context_writes_once(): void {
		[ $set, $context ] = $this->option_set(
			[
				'a' => [ 'type' => 'text' ],
				'b' => [ 'type' => 'text' ],
				'c' => [ 'type' => 'text' ],
			]
		);

		$set->save( [ 'a' => '1', 'b' => '2', 'c' => '3' ] );

		$this->assertSame( [ 'a' => '1', 'b' => '2', 'c' => '3' ], $context->values() );
	}

	/**
	 * A number outside its range is clamped, not stored as given.
	 */
	public function test_number_is_clamped(): void {
		[ $set, $context ] = $this->option_set(
			[ 'count' => [ 'type' => 'number', 'min' => 1, 'max' => 5 ] ]
		);

		$set->save( [ 'count' => '99' ] );

		$this->assertSame( 5, $context->values()['count'] );
	}

	/**
	 * An unchecked checkbox stores zero rather than being treated as absent.
	 *
	 * Treating 0 as empty is how a deliberately saved "off" reverts to a
	 * default on the next load.
	 */
	public function test_zero_is_a_value_not_emptiness(): void {
		[ $set, $context ] = $this->option_set(
			[ 'enabled' => [ 'type' => 'checkbox', 'default' => 1 ] ]
		);

		$set->save( [ 'enabled' => '0' ] );

		$this->assertArrayHasKey( 'enabled', $context->values() );
		$this->assertSame( 0, $context->values()['enabled'] );
	}

	/**
	 * A `select2` field is a searchable select, not a plain one.
	 *
	 * It was an alias for `select`, so every field written as `select2` — the
	 * name the settings library used for a searchable dropdown — rendered as
	 * a bare native dropdown with no search box. The class the JS looks for
	 * was never emitted, so nothing upgraded it.
	 */
	public function test_select2_renders_the_searchable_control(): void {
		[ $set ] = $this->option_set(
			[
				'plain'  => [
					'type'    => 'select',
					'options' => [ 'a' => 'A' ],
				],
				'search' => [
					'type'    => 'select2',
					'options' => [ 'a' => 'A' ],
				],
				'opt_in' => [
					'type'       => 'select',
					'searchable' => true,
					'options'    => [ 'a' => 'A' ],
				],
			]
		);

		$rendered = [];

		foreach ( $set->fields() as $field ) {
			$rendered[ $field->key() ] = $set->render_field( $field );
		}

		$this->assertStringNotContainsString( 'field-kit__select--enhanced', $rendered['plain'] );
		$this->assertStringContainsString( 'field-kit__select--enhanced', $rendered['search'] );
		$this->assertStringContainsString( 'field-kit__select--enhanced', $rendered['opt_in'] );
	}

	/**
	 * A `select2` value is still checked against its own options.
	 */
	public function test_select2_rejects_a_value_outside_its_options(): void {
		[ $set, $context ] = $this->option_set(
			[
				'choice' => [
					'type'    => 'select2',
					'options' => [
						'a' => 'A',
						'b' => 'B',
					],
				],
			]
		);

		$set->save( [ 'choice' => 'c' ] );
		$this->assertSame( [], $context->values() );

		$set->save( [ 'choice' => 'b' ] );
		$this->assertSame( [ 'choice' => 'b' ], $context->values() );
	}

	/**
	 * A relational field with nothing selected stores nothing.
	 *
	 * Zero is a value to the field set, deliberately — an unticked checkbox
	 * stores it. A relational type returning it for an empty select is how a
	 * page nobody touched ends up holding a post id of 0 for every post,
	 * page, user and term field on it. The media types learned this first;
	 * these had not.
	 */
	public function test_an_empty_relational_field_stores_nothing(): void {
		[ $set, $context ] = $this->option_set(
			[
				'related_post' => [ 'type' => 'post' ],
				'author'       => [ 'type' => 'user' ],
				'category'     => [ 'type' => 'taxonomy' ],
			]
		);

		$set->save(
			[
				'related_post' => '',
				'author'       => '0',
			]
		);

		$this->assertSame( [], $context->values() );
	}

	/**
	 * A relational field that has a selection stores the id.
	 */
	public function test_a_relational_field_stores_its_id(): void {
		[ $set, $context ] = $this->option_set( [ 'related_post' => [ 'type' => 'post' ] ] );

		$set->save( [ 'related_post' => '42' ] );

		$this->assertSame( [ 'related_post' => 42 ], $context->values() );
	}

	/**
	 * A field whose conditions fail is removed, not stored.
	 *
	 * The script hides it, but nothing stops a submission carrying it.
	 */
	public function test_a_hidden_field_is_not_stored(): void {
		[ $set, $context ] = $this->option_set(
			[
				'enabled' => [ 'type' => 'checkbox' ],
				'detail'  => [ 'type' => 'text', 'show_when' => [ 'enabled' => 1 ] ],
			]
		);

		$set->save( [ 'enabled' => '1', 'detail' => 'kept' ] );
		$this->assertSame( 'kept', $context->values()['detail'] );

		// Submitted again with the trigger off but the value still posted.
		$set->save( [ 'enabled' => '0', 'detail' => 'should not survive' ] );
		$this->assertArrayNotHasKey( 'detail', $context->values() );
	}

	/**
	 * A layout field stores nothing.
	 */
	public function test_layout_fields_are_never_stored(): void {
		[ $set, $context ] = $this->option_set(
			[
				'intro' => [ 'type' => 'heading', 'label' => 'Intro' ],
				'rule'  => [ 'type' => 'separator' ],
				'real'  => [ 'type' => 'text' ],
			]
		);

		$set->save( [ 'intro' => 'x', 'rule' => 'y', 'real' => 'z' ] );

		$this->assertSame( [ 'real' => 'z' ], $context->values() );
	}

	/**
	 * Input is unslashed once, at the boundary.
	 */
	public function test_input_is_unslashed_once(): void {
		[ $set, $context ] = $this->option_set( [ 'name' => [ 'type' => 'text' ] ] );

		$set->save( [ 'name' => "O\\'Brien" ] );

		$this->assertSame( "O'Brien", $context->values()['name'] );
	}

	/**
	 * Nested values survive the round trip.
	 */
	public function test_group_round_trips(): void {
		[ $set, $context ] = $this->option_set(
			[
				'address' => [
					'type'   => 'group',
					'fields' => [
						'street' => [ 'type' => 'text' ],
						'active' => [ 'type' => 'checkbox' ],
					],
				],
			]
		);

		$set->save( [ 'address' => [ 'street' => '1 High St', 'active' => '1' ] ] );

		$this->assertSame(
			[ 'street' => '1 High St', 'active' => 1 ],
			$context->values()['address']
		);
	}

	/**
	 * A repeater drops rows that hold nothing and reindexes the rest.
	 */
	public function test_repeater_drops_empty_rows_and_reindexes(): void {
		[ $set, $context ] = $this->option_set(
			[
				'rows' => [
					'type'   => 'repeater',
					'fields' => [ 'name' => [ 'type' => 'text' ] ],
				],
			]
		);

		$set->save(
			[
				'rows' => [
					0 => [ 'name' => 'one' ],
					2 => [ 'name' => '' ],
					5 => [ 'name' => 'three' ],
				],
			]
		);

		$this->assertSame(
			[ [ 'name' => 'one' ], [ 'name' => 'three' ] ],
			$context->values()['rows']
		);
	}

	/**
	 * Meta is slashed on the way in, because the meta API unslashes it.
	 */
	public function test_meta_context_slashes_for_the_meta_api(): void {
		$context = new PostMetaContext( 'fk_' );
		$set     = new FieldSet( [ 'name' => [ 'type' => 'text' ] ], $context );

		$set->save( [ 'name' => 'Widget' ], 42 );

		$this->assertSame( 'Widget', get_metadata( 'post', 42, 'fk_name', true ) );
	}

	/**
	 * A set reports every handle its fields need, without duplicates.
	 */
	public function test_dependencies_are_collected_and_deduplicated(): void {
		[ $set ] = $this->option_set(
			[
				'one' => [ 'type' => 'color' ],
				'two' => [ 'type' => 'color' ],
			]
		);

		// jquery is listed explicitly: wp-color-picker is a jQuery plugin,
		// and enqueueing it without jQuery leaves a plain text input and no
		// error anywhere.
		$this->assertSame( [ 'jquery', 'wp-color-picker' ], $set->dependencies()['scripts'] );
	}

	/**
	 * Code fields report the language they need an editor for.
	 *
	 * A code editor is not a plain script handle: wp_enqueue_code_editor()
	 * has to be called per language to pull in the right mode and linter, so
	 * the set reports languages rather than handles and the asset registrar
	 * makes the call.
	 */
	public function test_code_editor_languages_are_collected(): void {
		[ $set ] = $this->option_set(
			[
				'css'   => [ 'type' => 'code', 'language' => 'text/css' ],
				'html'  => [ 'type' => 'code', 'language' => 'text/html' ],
				'again' => [ 'type' => 'code', 'language' => 'text/css' ],
			]
		);

		$this->assertSame(
			[ 'text/css', 'text/html' ],
			$set->dependencies()['code_editors']
		);
	}

}
