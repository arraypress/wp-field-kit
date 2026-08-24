<?php
/**
 * Badge tests.
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
use ArrayPress\FieldKit\Support\Badge;
use PHPUnit\Framework\TestCase;

/**
 * A badge marks a field this install cannot use, and locks it.
 *
 * The condition is inverted from what reads naturally — `disabled` hides the
 * badge, because it answers "does this install already have the feature?" —
 * so it is worth pinning down in both directions.
 */
final class BadgeTest extends TestCase {

	/**
	 * Reset the stubbed option store.
	 */
	protected function setUp(): void {
		$GLOBALS['fk_options'] = [];
	}

	/**
	 * Build a field.
	 *
	 * @param array<string, mixed> $config Field configuration.
	 * @param string               $type   Type id.
	 *
	 * @return Field
	 */
	private function field( array $config = [], string $type = 'text' ): Field {
		$registry = new Registry();

		return new Field( 'feature', $registry->get( $type ), $config, null );
	}

	/**
	 * A string badge is the short form of the array one.
	 */
	public function test_a_string_badge_resolves(): void {
		$this->assertSame(
			[
				'text'  => 'Pro',
				'url'   => '',
				'class' => '',
				'icon'  => '',
			],
			Badge::resolve( 'Pro' )
		);
	}

	/**
	 * Nothing to show resolves to null.
	 */
	public function test_an_absent_badge_resolves_to_null(): void {
		foreach ( [ null, '', [], [ 'text' => '' ], 0, false ] as $badge ) {
			$this->assertNull( Badge::resolve( $badge ) );
		}
	}

	/**
	 * `disabled` hides the badge — it means the install already has this.
	 */
	public function test_disabled_hides_the_badge(): void {
		$this->assertNull(
			Badge::resolve(
				[
					'text'     => 'Pro',
					'disabled' => true,
				]
			)
		);
	}

	/**
	 * A callable is evaluated, so the answer can be a licence check made at
	 * render time rather than at registration.
	 */
	public function test_a_callable_decides_whether_to_show(): void {
		$this->assertNull(
			Badge::resolve(
				[
					'text'     => 'Pro',
					'disabled' => static fn() => true,
				]
			)
		);

		$this->assertNotNull(
			Badge::resolve(
				[
					'text'     => 'Pro',
					'disabled' => static fn() => false,
				]
			)
		);
	}

	/**
	 * A badge with a URL opens elsewhere, and says so.
	 */
	public function test_a_linked_badge_opens_in_a_new_tab_and_says_so(): void {
		$html = Badge::render(
			(array) Badge::resolve(
				[
					'text' => 'Pro',
					'url'  => 'https://example.test/upgrade',
				]
			)
		);

		$this->assertStringContainsString( 'href="https://example.test/upgrade"', $html );
		$this->assertStringContainsString( 'rel="noopener noreferrer"', $html );
		$this->assertStringContainsString( 'class="screen-reader-text"', $html );
	}

	/**
	 * A badge without a URL is not a link.
	 */
	public function test_an_unlinked_badge_is_a_span(): void {
		$html = Badge::render( (array) Badge::resolve( 'Pro' ) );

		$this->assertStringStartsWith( '<span', $html );
		$this->assertStringNotContainsString( '<a', $html );
	}

	/**
	 * Badge text is escaped.
	 */
	public function test_badge_text_is_escaped(): void {
		$html = Badge::render( (array) Badge::resolve( 'Pro <script>alert(1)</script>' ) );

		$this->assertStringNotContainsString( '<script', $html );
	}

	/**
	 * A showing badge appears in the label and disables the control.
	 */
	public function test_a_badged_field_renders_locked(): void {
		$html = ( new Renderer() )->render(
			$this->field(
				[
					'label' => 'Advanced sync',
					'badge' => 'Pro',
				]
			)
		);

		$this->assertStringContainsString( 'field-kit__badge', $html );
		$this->assertStringContainsString( 'field-kit__field--locked', $html );
		$this->assertStringContainsString( 'disabled', $html );
	}

	/**
	 * A hidden badge leaves the field alone.
	 */
	public function test_a_field_whose_badge_is_hidden_is_not_locked(): void {
		$html = ( new Renderer() )->render(
			$this->field(
				[
					'label' => 'Advanced sync',
					'badge' => [
						'text'     => 'Pro',
						'disabled' => true,
					],
				]
			)
		);

		$this->assertStringNotContainsString( 'field-kit__badge', $html );
		$this->assertStringNotContainsString( 'field-kit__field--locked', $html );
		$this->assertStringNotContainsString( 'disabled', $html );
	}

	/**
	 * A grouped field's badge goes in the legend.
	 */
	/**
	 * The badge goes in the label, and nowhere else.
	 *
	 * It first went beside the control whenever no label was drawn, which is
	 * every field on a settings page — a table row draws its label in its own
	 * header cell. That put a pill hard against the side of every input. A
	 * caller that draws its own heading draws its own badge, from
	 * Badge::for_field(), so the renderer must draw none.
	 */
	public function test_the_badge_goes_in_the_label_and_nowhere_else(): void {
		$renderer = new Renderer();

		$field = $this->field(
			[
				'label' => 'Advanced sync',
				'badge' => 'Pro',
			]
		);

		$with = $renderer->render( $field, '', true );

		$this->assertSame( 1, substr_count( $with, 'field-kit__badge' ) );
		$this->assertMatchesRegularExpression( '/<label[^>]*>.*field-kit__badge.*<\/label>/s', $with );

		// The caller owns the heading, so it owns the badge.
		$this->assertStringNotContainsString( 'field-kit__badge', $renderer->render( $field, '', false ) );

		// And it is still available to that caller.
		$this->assertStringContainsString( 'field-kit__badge', Badge::for_field( $field ) );
	}

	/**
	 * A self-labelling control gets its badge after the control.
	 *
	 * A toggle puts its own text inside its own label, so there is nothing to
	 * append to. Before the control it reads as a label for the box.
	 */
	public function test_a_self_labelling_control_gets_its_badge_after_the_control(): void {
		$html = ( new Renderer() )->render(
			$this->field(
				[
					'label' => 'Advanced sync',
					'badge' => 'Pro',
				],
				'toggle'
			)
		);

		$this->assertSame( 1, substr_count( $html, 'field-kit__badge' ) );
		$this->assertGreaterThan( strpos( $html, '<input' ), strpos( $html, 'field-kit__badge' ) );
	}

	/**
	 * A group's badge goes in its legend, or nowhere.
	 */
	public function test_a_group_badge_goes_in_the_legend_or_nowhere(): void {
		$renderer = new Renderer();

		$field = $this->field(
			[
				'label'   => 'Modes',
				'options' => [ 'a' => 'A' ],
				'badge'   => 'Pro',
			],
			'radio'
		);

		$this->assertMatchesRegularExpression(
			'/<legend[^>]*>.*field-kit__badge.*<\/legend>/s',
			$renderer->render( $field, '', true )
		);

		// A hidden legend would hide it, which for a badge is the same as
		// leaving it out — and the caller draws its own.
		$this->assertStringNotContainsString( 'field-kit__badge', $renderer->render( $field, '', false ) );
	}

	/**
	 * A locked field's stored value survives a save.
	 *
	 * The control is disabled, so the browser sends nothing for it. Left to
	 * the ordinary rules that reads as "cleared" — so an install that lost a
	 * licence would have its premium settings wiped by the next unrelated
	 * save, and get them back as blanks when the licence returned.
	 */
	public function test_a_locked_fields_value_survives_a_save(): void {
		$context = new OptionContext( 'fk_test' );

		$GLOBALS['fk_options']['fk_test'] = [
			'feature' => 'configured-while-licensed',
			'other'   => 'x',
		];

		$set = new FieldSet(
			[
				'feature' => [
					'type'  => 'text',
					'badge' => 'Pro',
				],
				'other'   => [ 'type' => 'text' ],
			],
			$context,
			'fk_test'
		);

		$set->save( [ 'other' => 'y' ] );

		$this->assertSame( 'configured-while-licensed', $GLOBALS['fk_options']['fk_test']['feature'] );
		$this->assertSame( 'y', $GLOBALS['fk_options']['fk_test']['other'] );
	}

	/**
	 * The same holds for a plainly disabled field.
	 */
	public function test_a_disabled_fields_value_survives_a_save(): void {
		$context = new OptionContext( 'fk_test' );

		$GLOBALS['fk_options']['fk_test'] = [ 'feature' => 'set-elsewhere' ];

		$set = new FieldSet(
			[
				'feature' => [
					'type'     => 'text',
					'disabled' => true,
				],
			],
			$context,
			'fk_test'
		);

		$set->save( [] );

		$this->assertSame( 'set-elsewhere', $GLOBALS['fk_options']['fk_test']['feature'] );
	}
}
