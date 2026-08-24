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
use ArrayPress\FieldKit\Context\ArrayContext;
use ArrayPress\FieldKit\FieldSet;
use ArrayPress\FieldKit\Actions\Actions;
use ArrayPress\FieldKit\Search\Sources;
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
	 * A table repeater draws its labels once, at the top.
	 *
	 * A stack of rows is right when a row is a handful of fields with long
	 * labels. It is wrong when a row is three short columns repeated twenty
	 * times — a tax rate, a price tier — where the labels belong once at the
	 * top and the rows want to line up under them.
	 */
	public function test_a_table_repeater_has_column_headers(): void {
		[ $set ] = $this->option_set(
			[
				'rates' => [
					'type'   => 'repeater',
					'layout' => 'table',
					'fields' => [
						'country' => [
							'type'  => 'text',
							'label' => 'Country',
						],
						'rate'    => [
							'type'  => 'number',
							'label' => 'Rate',
						],
					],
				],
			]
		);

		$html = $set->render_field( $set->field( 'rates' ) );

		$this->assertStringContainsString( 'wp-list-table', $html );
		$this->assertStringContainsString( '<th scope="col">Country</th>', $html );
		$this->assertStringContainsString( '<th scope="col">Rate</th>', $html );

		// The header is the label, so a cell does not repeat it.
		$this->assertStringNotContainsString( '<label for="rates_row0_country">Country</label>', $html );
	}

	/**
	 * The template's row is inside the table.
	 *
	 * A <tr> inside a <template> that sits in a <div> is dropped by the HTML
	 * parser — template content is parsed in the context the template appears
	 * in — so the row to clone would simply not be there and Add row would
	 * add nothing.
	 */
	public function test_a_table_repeaters_template_is_inside_the_table(): void {
		[ $set ] = $this->option_set(
			[
				'rates' => [
					'type'   => 'repeater',
					'layout' => 'table',
					'fields' => [ 'country' => [ 'type' => 'text' ] ],
				],
			]
		);

		$html = $set->render_field( $set->field( 'rates' ) );

		$template = strpos( $html, '<template' );
		$closes   = strpos( $html, '</table>' );

		$this->assertIsInt( $template );
		$this->assertIsInt( $closes );
		$this->assertLessThan( $closes, $template, 'The template is outside the table, so its row will be dropped.' );
	}

	/**
	 * A stacked repeater is still the default.
	 */
	public function test_a_repeater_is_stacked_unless_asked_otherwise(): void {
		[ $set ] = $this->option_set(
			[
				'rows' => [
					'type'   => 'repeater',
					'fields' => [ 'name' => [ 'type' => 'text' ] ],
				],
			]
		);

		$html = $set->render_field( $set->field( 'rows' ) );

		$this->assertStringContainsString( '<ol class="field-kit__repeater-rows"', $html );
		$this->assertStringNotContainsString( 'wp-list-table', $html );
	}

	/**
	 * A table repeater saves the same way a stacked one does.
	 */
	public function test_a_table_repeater_saves(): void {
		[ $set, $context ] = $this->option_set(
			[
				'rates' => [
					'type'   => 'repeater',
					'layout' => 'table',
					'fields' => [
						'country' => [ 'type' => 'text' ],
						'rate'    => [ 'type' => 'number' ],
					],
				],
			]
		);

		$set->save(
			[
				'rates' => [
					[
						'country' => 'IE',
						'rate'    => '23',
					],
					[
						'country' => 'GB',
						'rate'    => '20',
					],
				],
			]
		);

		$this->assertSame(
			[
				[
					'country' => 'IE',
					'rate'    => 23,
				],
				[
					'country' => 'GB',
					'rate'    => 20,
				],
			],
			$context->values()['rates']
		);
	}

	/**
	 * A creatable control keeps a value its options never offered.
	 *
	 * That is the whole difference between a picker and a tag input. A
	 * select's option list is normally an allow-list, and leaving that in
	 * place would have discarded every value anyone created — silently, on
	 * save, with the control showing it right up until the reload.
	 */
	public function test_a_creatable_select_keeps_an_invented_value(): void {
		[ $set, $context ] = $this->option_set(
			[
				'picker' => [
					'type'    => 'select2',
					'options' => [ 'a' => 'A' ],
				],
				'tags'   => [
					'type'      => 'select2',
					'creatable' => true,
					'multiple'  => true,
					'options'   => [ 'a' => 'A' ],
				],
			]
		);

		$set->save(
			[
				'picker' => 'invented',
				'tags'   => [ 'a', 'invented' ],
			]
		);

		// The picker checks against its options; the tag input does not.
		$this->assertArrayNotHasKey( 'picker', $context->values() );
		$this->assertSame( [ 'a', 'invented' ], $context->values()['tags'] );
	}

	/**
	 * A creatable relational field stores what was typed, not absint() of it.
	 *
	 * A new tag has no id yet. absint() turns it into 0, and the field set
	 * treats 0 as a value, so it would have stored a row of zeroes.
	 */
	public function test_a_creatable_relational_field_keeps_text(): void {
		[ $set, $context ] = $this->option_set(
			[
				'related' => [
					'type'      => 'post',
					'creatable' => true,
					'multiple'  => true,
				],
			]
		);

		$set->save( [ 'related' => [ '42', 'A brand new thing' ] ] );

		$this->assertSame( [ '42', 'A brand new thing' ], $context->values()['related'] );
	}

	/**
	 * A created value labels itself when the field renders again.
	 *
	 * It never had an id to resolve, so without this a tag someone added
	 * vanishes from the control on the next load while still being stored.
	 */
	public function test_a_created_value_survives_a_reload(): void {
		[ $set ] = $this->option_set(
			[
				'related' => [
					'type'      => 'post',
					'creatable' => true,
					'multiple'  => true,
				],
			]
		);

		$GLOBALS['fk_options']['fk_test'] = [ 'related' => [ 'A brand new thing' ] ];

		$html = $set->render_field( $set->field( 'related' ) );

		$this->assertStringContainsString( 'A brand new thing', $html );
		$this->assertStringContainsString( 'data-creatable="true"', $html );
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


	/**
	 * A field's own sanitize_callback replaces its type's.
	 *
	 * The key is listed on Field as common, so a consumer reading that list
	 * expects it to work on any field. Only `custom` honoured it, which made
	 * it true of one type and documented for all of them.
	 *
	 * Replaces rather than runs after: someone who supplies one has an
	 * opinion about the whole value, and cleaning it first would mean they
	 * never see what was actually submitted.
	 */
	public function test_a_sanitize_callback_replaces_the_types(): void {
		$context = new ArrayContext( [] );

		$set = new FieldSet(
			[
				'count' => [
					'type'              => 'number',
					'max'               => 10,
					'sanitize_callback' => static fn( $value ) => 'saw ' . $value,
				],
				'plain' => [ 'type' => 'number', 'max' => 10 ],
			],
			$context,
			''
		);

		$set->save( [ 'count' => '9999', 'plain' => '9999' ] );

		// The callback saw the raw value, not one already clamped to 10.
		$this->assertSame( 'saw 9999', $context->values()['count'] );

		// And a field without one is still clamped by its type.
		$this->assertSame( 10, $context->values()['plain'] );
	}

	/**
	 * A field with a search callback registers a source and points at it.
	 *
	 * Registration happens when the set is built rather than when the field
	 * renders, because the request that searches is not the request that
	 * rendered — the combobox asks the endpoint on its own, and a source that
	 * only exists during a render is gone by then.
	 */
	public function test_a_search_callback_registers_a_source(): void {
		[ $set ] = $this->option_set(
			[
				'customer' => [
					'type'            => 'ajax',
					'label'           => 'Customer',
					'search_callback' => static fn( $term, $ids = null ) => [ 1 => 'Ada' ],
				],
			]
		);

		$name = 'fk_test_customer';

		$this->assertTrue( Sources::shared()->has( $name ) );
		$this->assertSame( $name, $set->field( 'customer' )->get( 'search_source' ) );
	}

	/**
	 * A consumer can name the source itself.
	 *
	 * A set built with no input prefix names a source after the field key
	 * alone, so two flyouts each with a `customer` field would name the same
	 * one and the second registration would answer the first's searches. A
	 * library that knows what distinguishes its sets supplies the name.
	 *
	 * Both halves, because a name honoured in one place and not the other is
	 * worse than neither: the field would emit a name nothing registered.
	 */
	public function test_a_named_source_is_registered_and_emitted_under_that_name(): void {
		$set = new FieldSet(
			[
				'customer' => [
					'type'            => 'ajax',
					'search_callback' => static fn( $term, $ids = null ) => [ 1 => 'Ada' ],
					'search_source'   => 'shop-order-customer',
				],
			],
			new ArrayContext(),
			''
		);

		$this->assertTrue( Sources::shared()->has( 'shop-order-customer' ) );
		$this->assertSame( 'shop-order-customer', $set->field( 'customer' )->get( 'search_source' ) );

		// And not under the name it would have derived.
		$this->assertFalse( Sources::shared()->has( 'customer' ) );
	}

	/**
	 * A field with an action handler registers it and points at it.
	 *
	 * Registration happens when the set is built rather than when the field
	 * renders, for the same reason a search source does: the request that
	 * presses the button is not the request that drew it, and a handler that
	 * only exists during a render is gone by then. That was the bug — a
	 * flyout's action button came back "Unknown action." every time.
	 */
	public function test_an_action_callback_registers_a_handler(): void {
		[ $set ] = $this->option_set(
			[
				'sync' => [
					'type'            => 'action_button',
					'label'           => 'Sync',
					'action_callback' => static fn() => [ 'message' => 'done' ],
				],
			]
		);

		$this->assertTrue( Actions::shared()->has( 'fk_test_sync_run' ) );
		$this->assertSame(
			[ 'run' => 'fk_test_sync_run' ],
			$set->field( 'sync' )->get( 'action_names' )
		);
	}

	/**
	 * A consumer can name the handlers itself.
	 *
	 * Same reasoning as a named search source: a set built with no input
	 * prefix derives the name from the field key alone, so two panels each
	 * with an `activate` button would name the same handler and the second
	 * registration would answer the first's presses.
	 *
	 * Both halves again, because a name honoured in one place and not the
	 * other emits a button pointing at nothing.
	 */
	public function test_named_actions_are_registered_and_emitted_under_those_names(): void {
		$set = new FieldSet(
			[
				'licence' => [
					'type'         => 'action_button',
					'actions'      => [
						'activate'   => static fn() => [ 'message' => 'on' ],
						'deactivate' => static fn() => [ 'message' => 'off' ],
					],
					'action_names' => [
						'activate'   => 'shop-licence-activate',
						'deactivate' => 'shop-licence-deactivate',
					],
				],
			],
			new ArrayContext(),
			''
		);

		$this->assertTrue( Actions::shared()->has( 'shop-licence-activate' ) );
		$this->assertTrue( Actions::shared()->has( 'shop-licence-deactivate' ) );

		$this->assertSame(
			[
				'activate'   => 'shop-licence-activate',
				'deactivate' => 'shop-licence-deactivate',
			],
			$set->field( 'licence' )->get( 'action_names' )
		);

		// And not under the names it would have derived.
		$this->assertFalse( Actions::shared()->has( 'licence_activate' ) );
	}
}