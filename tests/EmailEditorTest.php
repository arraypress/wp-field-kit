<?php
/**
 * Email panel tests.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Tests;

use ArrayPress\FieldKit\Context\OptionContext;
use ArrayPress\FieldKit\Field;
use ArrayPress\FieldKit\FieldSet;
use ArrayPress\FieldKit\Registry;
use ArrayPress\FieldKit\Renderer;
use PHPUnit\Framework\TestCase;

/**
 * An email is four controls, a tag reference and two actions. A settings-table
 * row is built for one control beside one label, so the panel has to say it
 * wants the whole row — and its parts have to be the same set at render time
 * and at save time, or a part is drawn and never stored.
 */
final class EmailEditorTest extends TestCase {

	/**
	 * Reset the stubbed option store.
	 */
	protected function setUp(): void {
		$GLOBALS['fk_options'] = [];
	}

	/**
	 * Build an email field.
	 *
	 * @param array<string, mixed> $config Field configuration.
	 * @param mixed                $value  Stored value.
	 *
	 * @return Field
	 */
	private function field( array $config = [], mixed $value = null ): Field {
		$registry = new Registry();
		$type     = $registry->get( 'email_editor' );

		return new Field(
			'receipt',
			$type,
			array_merge( $type->defaults(), [ 'label' => 'Purchase receipt' ], $config ),
			$value
		);
	}

	/**
	 * It renders core's panel, not a bare stack of inputs.
	 */
	public function test_it_renders_cores_accordion(): void {
		$html = ( new Renderer() )->render( $this->field() );

		$this->assertStringContainsString( 'class="privacy-settings-accordion field-kit__accordion field-kit__email"', $html );
		$this->assertStringContainsString( 'privacy-settings-accordion-trigger', $html );
		$this->assertStringContainsString( 'class="field-kit__accordion-panel"', $html );
		$this->assertStringNotContainsString( 'postbox', $html );
	}

	/**
	 * The panel is a named region, and the name is its own heading.
	 *
	 * There is no single control here to point a `<label for>` at, and a
	 * label naming the whole panel would be read before every control in it.
	 */
	public function test_the_panel_is_a_named_region(): void {
		$html = ( new Renderer() )->render( $this->field() );

		$this->assertStringContainsString( 'role="region"', $html );
		$this->assertStringContainsString( 'aria-labelledby="receipt__title"', $html );
		$this->assertStringContainsString( 'id="receipt__title"', $html );
		$this->assertStringContainsString( '<span class="title">Purchase receipt</span>', $html );
		$this->assertStringNotContainsString( '<label for="receipt"', $html );
	}

	/**
	 * A description is referenced by the region.
	 *
	 * The renderer builds the association for a control this field does not
	 * have, so without the region taking it the description sits below the
	 * panel referenced by nothing.
	 */
	public function test_the_region_references_its_description(): void {
		$html = ( new Renderer() )->render( $this->field( [ 'description' => 'Sent after a purchase.' ] ) );

		$this->assertStringContainsString( 'aria-describedby="receipt__description"', $html );
		$this->assertStringContainsString( 'id="receipt__description"', $html );
	}

	/**
	 * It wants the whole row.
	 */
	public function test_it_spans_the_row(): void {
		$this->assertTrue( $this->field()->type()->spans_row() );
		$this->assertTrue( $this->field()->type()->is_self_labelling() );
		$this->assertFalse( $this->field()->type()->is_grouped() );
	}

	/**
	 * A subject and a body always; a recipient and a heading only when asked.
	 *
	 * An admin notice has a fixed recipient and a plain-text email has no
	 * heading. An input for something the sender ignores is worse than none.
	 */
	public function test_optional_parts_are_opt_in(): void {
		$plain = ( new Renderer() )->render( $this->field() );

		$this->assertStringContainsString( 'name="receipt[subject]"', $plain );
		$this->assertStringContainsString( 'receipt[body]', $plain );
		$this->assertStringNotContainsString( 'name="receipt[recipient]"', $plain );
		$this->assertStringNotContainsString( 'name="receipt[heading]"', $plain );

		$full = ( new Renderer() )->render(
			$this->field(
				[
					'recipient' => true,
					'heading'   => true,
				]
			)
		);

		$this->assertStringContainsString( 'name="receipt[recipient]"', $full );
		$this->assertStringContainsString( 'name="receipt[heading]"', $full );
	}

	/**
	 * The collapse control is core's accordion chevron.
	 *
	 * The same trigger the collapsible sections draw, so the two fold the
	 * same way and edit.css styles the chevron; nothing here has to restate
	 * a glyph core scopes to some other screen.
	 */
	public function test_the_collapse_control_is_cores_chevron(): void {
		$html = ( new Renderer() )->render( $this->field() );

		$this->assertStringContainsString( '<span class="icon"></span>', $html );
		$this->assertStringContainsString( 'aria-expanded="true"', $html );
		$this->assertMatchesRegularExpression( '/aria-controls="([^"]+)".*<div id="\\1" class="field-kit__accordion-panel">/s', $html );
	}

	/**
	 * The panel has room at the top.
	 *
	 * The first control used to sit against the header's border; the
	 * accordion panel the sections share pads on every side.
	 */
	public function test_the_panel_has_padding_at_the_top(): void {
		$css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/field-kit.css' );

		preg_match( '/\.field-kit__accordion-panel \{([^}]*)\}/', $css, $rule );

		$this->assertNotEmpty( $rule, 'The panel body has no rule.' );
		$this->assertDoesNotMatchRegularExpression( '/padding:\s*0\s/', $rule[1] );
	}

	/**
	 * Every part says what it is for.
	 *
	 * A stack of four unlabelled boxes in a panel is worse than the same four
	 * in a table, because the table at least puts the name beside each one.
	 */
	public function test_every_part_has_a_description(): void {
		$field = $this->field(
			[
				'recipient' => true,
				'heading'   => true,
			]
		);

		$parts = ( new \ReflectionObject( $field->type() ) )->getMethod( 'parts' );

		foreach ( $parts->invoke( $field->type(), $field ) as $key => $config ) {
			$this->assertNotSame(
				'',
				(string) ( $config['description'] ?? '' ),
				sprintf( 'The %s part has no description.', $key )
			);
		}
	}

	/**
	 * The parts drawn are the parts saved.
	 *
	 * Rendering and sanitizing resolve them separately, and a set that
	 * disagreed would draw a control whose value is silently dropped.
	 */
	public function test_every_rendered_part_is_saved(): void {
		$context = new OptionContext( 'fk_test' );

		$set = new FieldSet(
			[
				'receipt' => [
					'type'      => 'email_editor',
					'label'     => 'Purchase receipt',
					'recipient' => true,
					'heading'   => true,
				],
			],
			$context,
			'fk_test'
		);

		$set->save(
			[
				'receipt' => [
					'recipient' => 'billing@example.test',
					'subject'   => 'Your receipt',
					'heading'   => 'Thanks!',
					'body'      => '<p>Here it is.</p>',
				],
			]
		);

		$stored = $GLOBALS['fk_options']['fk_test']['receipt'];

		$this->assertSame( 'billing@example.test', $stored['recipient'] );
		$this->assertSame( 'Your receipt', $stored['subject'] );
		$this->assertSame( 'Thanks!', $stored['heading'] );
		$this->assertStringContainsString( 'Here it is.', $stored['body'] );
	}

	/**
	 * A part that was not offered is not stored either.
	 */
	public function test_a_part_that_was_not_offered_is_not_stored(): void {
		$context = new OptionContext( 'fk_test' );

		$set = new FieldSet(
			[ 'receipt' => [ 'type' => 'email_editor' ] ],
			$context,
			'fk_test'
		);

		$set->save(
			[
				'receipt' => [
					'subject'   => 'Your receipt',
					'recipient' => 'sneaked@example.test',
				],
			]
		);

		$this->assertArrayNotHasKey( 'recipient', $GLOBALS['fk_options']['fk_test']['receipt'] );
	}

	/**
	 * Stored values come back into their parts.
	 */
	public function test_stored_values_round_trip(): void {
		$html = ( new Renderer() )->render(
			$this->field(
				[ 'recipient' => true ],
				[
					'recipient' => 'billing@example.test',
					'subject'   => 'Your receipt',
				]
			)
		);

		$this->assertStringContainsString( 'value="billing@example.test"', $html );
		$this->assertStringContainsString( 'value="Your receipt"', $html );
	}

	/**
	 * Merge tags reach the body editor rather than a list of their own.
	 *
	 * They used to be a permanent block of codes under the editor, which is
	 * a lot of panel for a reference nobody reads twice. They belong beside
	 * Add Media, where someone writing the body already is.
	 */
	public function test_merge_tags_are_handed_to_the_body(): void {
		$field = $this->field(
			[
				'tags' => [
					[
						'name' => 'Customer name',
						'tag'  => '{customer_name}',
					],
				],
			]
		);

		$parts = ( new \ReflectionObject( $field->type() ) )->getMethod( 'parts' );

		$resolved = $parts->invoke( $field->type(), $field );

		$this->assertArrayHasKey( 'tags', $resolved['body'] );
		$this->assertSame( $field->get( 'tags' ), $resolved['body']['tags'] );
	}

	/**
	 * The panel no longer carries a tag list of its own.
	 */
	public function test_the_panel_has_no_inline_tag_list(): void {
		$html = ( new Renderer() )->render( $this->field( [ 'tags' => [ '{site_name}' ] ] ) );

		$this->assertStringNotContainsString( 'field-kit__email-tags', $html );
	}

	/**
	 * A button is only drawn for a handler that was registered.
	 *
	 * One for a handler nobody registered posts to a 404, which looks exactly
	 * like a button that does nothing.
	 */
	public function test_actions_are_only_drawn_for_registered_handlers(): void {
		$this->assertStringNotContainsString(
			'field-kit__email-action"',
			( new Renderer() )->render( $this->field() )
		);

		$html = ( new Renderer() )->render(
			$this->field( [ 'action_names' => [ 'preview' => 'receipt_preview' ] ] )
		);

		$this->assertSame( 1, substr_count( $html, 'field-kit__email-action"' ) );
		$this->assertStringContainsString( 'data-action="receipt_preview"', $html );
	}

	/**
	 * A panel can start closed.
	 */
	public function test_a_panel_can_start_closed(): void {
		$html = ( new Renderer() )->render( $this->field( [ 'collapsed' => true ] ) );

		$this->assertStringContainsString( 'class="field-kit__accordion-panel" hidden>', $html );
		$this->assertStringContainsString( 'aria-expanded="false"', $html );
	}

	/**
	 * The parts can be replaced wholesale.
	 */
	public function test_the_parts_can_be_replaced(): void {
		$html = ( new Renderer() )->render(
			$this->field(
				[
					'fields' => [
						'sms' => [
							'type'  => 'text',
							'label' => 'SMS text',
						],
					],
				]
			)
		);

		$this->assertStringContainsString( 'name="receipt[sms]"', $html );
		$this->assertStringNotContainsString( 'name="receipt[subject]"', $html );
	}

	/**
	 * A handler's preview has somewhere to land.
	 *
	 * The script fills `.field-kit__action-preview` with whatever a handler
	 * returns as data.html. The stylesheet styled that region and nothing
	 * rendered it, so a Preview button reported success with nothing to see.
	 */
	public function test_the_preview_region_is_rendered(): void {
		$registry = new Registry();

		foreach ( [ 'email_editor', 'action_button' ] as $type ) {
			$config = [
				'label'        => 'Demo',
				'input_name'   => 'demo',
				'action_names' => [ 'preview' => 'demo_preview', 'run' => 'demo_run' ],
			];

			$html = ( new Renderer() )->render( new Field( 'demo', $registry->get( $type ), $config, null ) );

			$this->assertStringContainsString( '<div class="field-kit__action-preview" hidden></div>', $html, $type );
		}
	}
}
