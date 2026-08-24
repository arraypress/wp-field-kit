<?php
/**
 * Creatable choice tests.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Tests;

use ArrayPress\FieldKit\Context\ArrayContext;
use ArrayPress\FieldKit\FieldSet;
use PHPUnit\Framework\TestCase;

/**
 * A choice a person can add to.
 *
 * The saving half worked all along. What did not was rendering the control
 * again afterwards: the configured options will never contain an invented
 * value, so it had no `<option>` to be selected on, vanished from the field,
 * looked like it had never saved — and was genuinely lost on the next save,
 * because a value with no option is not in the form that submits.
 */
final class CreatableTest extends TestCase {

	/**
	 * Render one field.
	 *
	 * @param array<string, mixed> $config Extra configuration.
	 * @param mixed                $stored What is stored.
	 *
	 * @return string
	 */
	private function render( array $config = [], mixed $stored = null ): string {
		$set = new FieldSet(
			[
				'tags' => array_merge(
					[
						'type'     => 'enhanced_select',
						'label'    => 'Tags',
						'multiple' => true,
						'options'  => [ 'a' => 'Alpha', 'b' => 'Beta' ],
					],
					$config
				),
			],
			new ArrayContext( null === $stored ? [] : [ 'tags' => $stored ] ),
			''
		);

		return $set->render_field( $set->field( 'tags' ) );
	}

	/**
	 * An invented value comes back with an option to sit on.
	 */
	public function test_an_invented_value_survives_a_reload(): void {
		$html = $this->render( [ 'creatable' => true ], [ 'a', 'a-word-i-typed' ] );

		$this->assertStringContainsString( '<option value="a-word-i-typed" selected>', $html );
		$this->assertStringContainsString( '<option value="a" selected>', $html );
	}

	/**
	 * Its value is its own label, since nobody chose it from a list.
	 */
	public function test_an_invented_value_labels_itself(): void {
		$this->assertStringContainsString(
			'>a-word-i-typed</option>',
			$this->render( [ 'creatable' => true ], [ 'a-word-i-typed' ] )
		);
	}

	/**
	 * A field that cannot invent values does not gain options for them.
	 *
	 * Storage may hold anything — an import, a filter, an older version of
	 * the configuration — and a plain select's options are its allow-list.
	 */
	public function test_a_plain_select_gains_nothing(): void {
		$html = $this->render( [], [ 'a', 'not-an-option' ] );

		$this->assertStringNotContainsString( 'not-an-option', $html );
	}

	/**
	 * Nothing stored renders no extra option.
	 *
	 * A value of nothing comes back as a single empty string rather than an
	 * empty list, and an option for that gives a multiple control one blank
	 * chip with a remove button and no label — on every empty creatable field
	 * on the page.
	 *
	 * @dataProvider emptyProvider
	 *
	 * @param mixed $stored What is stored.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'emptyProvider' )]
	public function test_nothing_stored_renders_no_option( mixed $stored ): void {
		$html = $this->render( [ 'creatable' => true ], $stored );

		$this->assertStringNotContainsString( '<option value="" selected>', $html );
		$this->assertSame( 2, substr_count( $html, '<option' ), 'An option appeared for a value nobody set.' );
	}

	/**
	 * The shapes an empty value arrives in.
	 *
	 * @return array<string, array{0: mixed}>
	 */
	public static function emptyProvider(): array {
		return [
			'never set'    => [ null ],
			'empty string' => [ '' ],
			'empty list'   => [ [] ],
		];
	}

	/**
	 * An option group's own options count as known.
	 *
	 * Otherwise every grouped option would be duplicated at the end of the
	 * list as if it had been invented.
	 */
	public function test_a_grouped_option_is_not_treated_as_invented(): void {
		$html = $this->render(
			[
				'creatable' => true,
				'options'   => [ 'Letters' => [ 'a' => 'Alpha', 'b' => 'Beta' ] ],
			],
			[ 'a' ]
		);

		$this->assertSame( 2, substr_count( $html, '<option' ) );
	}

	/**
	 * And the value still saves, which is the half that always worked.
	 */
	public function test_an_invented_value_saves(): void {
		$context = new ArrayContext();

		$set = new FieldSet(
			[
				'tags' => [
					'type'      => 'enhanced_select',
					'multiple'  => true,
					'creatable' => true,
					'options'   => [ 'a' => 'Alpha' ],
				],
			],
			$context,
			''
		);

		$set->save( [ 'tags' => [ 'a', 'invented' ] ] );

		$this->assertSame( [ 'a', 'invented' ], $context->values()['tags'] );
	}
}
