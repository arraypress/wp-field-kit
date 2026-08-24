<?php
/**
 * Combobox placeholder tests.
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
 * A single select has an empty option — "— Select —" — and the combobox
 * borrows its label, which is what a native select shows. A multiple select
 * has no empty option at all: choosing several values is what it is for, so
 * an empty one would be meaningless. The result was a combobox that fell back
 * to nothing and rendered as a blank box with no indication it could be typed
 * in — and a creatable one gave no hint that typing a value it had never
 * heard of was allowed, which is the entire feature.
 *
 * So the placeholder is derived when none is configured. What it says depends
 * on where the options come from: a list already on the page is chosen from,
 * a list fetched as you type has to be searched for first.
 */
final class PlaceholderTest extends TestCase {

	/**
	 * Render a field and return the markup.
	 *
	 * @param string               $type   Type id.
	 * @param array<string, mixed> $config Field configuration.
	 *
	 * @return string
	 */
	private function render( string $type, array $config = [] ): string {
		$field = new Field(
			'demo',
			( new Registry() )->get( $type ),
			array_merge(
				[
					'label'      => 'Demo',
					'input_name' => 'demo',
					'options'    => [ 'a' => 'Alpha', 'b' => 'Beta' ],
				],
				$config
			),
			null
		);

		return ( new Renderer() )->render( $field );
	}

	/**
	 * The placeholder a control hands the combobox.
	 *
	 * @param string               $type   Type id.
	 * @param array<string, mixed> $config Field configuration.
	 *
	 * @return string|null Null when none is emitted.
	 */
	private function placeholder( string $type, array $config = [] ): ?string {
		return preg_match(
			'/data-placeholder="([^"]*)"/',
			$this->render( $type, $config ),
			$match
		) ? html_entity_decode( $match[1], ENT_QUOTES ) : null;
	}

	/**
	 * A plain select is not a combobox and says nothing to one.
	 */
	public function test_a_plain_select_emits_no_placeholder(): void {
		$this->assertNull( $this->placeholder( 'select' ) );
	}

	/**
	 * A single searchable select falls back to its own empty option.
	 *
	 * Deliberately left alone: the empty option is real markup a native
	 * select shows, and inventing a second source of the same text is how the
	 * two drift apart.
	 */
	public function test_a_single_local_select_leaves_the_empty_option_to_speak(): void {
		$this->assertNull( $this->placeholder( 'select2' ) );

		$this->assertStringContainsString(
			'<option value="">',
			$this->render( 'select2' ),
			'There is no empty option for the combobox to fall back to.'
		);
	}

	/**
	 * A multiple select has no empty option, so it needs a placeholder.
	 */
	public function test_a_multiple_select_says_it_takes_more_than_one(): void {
		$html = $this->render( 'select2', [ 'multiple' => true ] );

		$this->assertStringNotContainsString(
			'<option value="">',
			$html,
			'A multiple select should have no empty option.'
		);

		$this->assertSame( 'Choose one or more', $this->placeholder( 'select2', [ 'multiple' => true ] ) );
	}

	/**
	 * A creatable control says that typing something new is allowed.
	 */
	public function test_a_creatable_select_says_a_new_value_can_be_typed(): void {
		$this->assertSame(
			'Choose, or type to add',
			$this->placeholder( 'select2', [ 'creatable' => true ] )
		);
	}

	/**
	 * A search control says to type, because nothing is on offer until you do.
	 *
	 * @dataProvider relationalProvider
	 *
	 * @param array<string, mixed> $config   Field configuration.
	 * @param string               $expected What it should say.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'relationalProvider' )]
	public function test_a_search_control_says_to_type( array $config, string $expected ): void {
		$this->assertSame( $expected, $this->placeholder( 'post', $config ) );
	}

	/**
	 * One case per shape a relational control comes in.
	 *
	 * @return array<string, array{0: array<string, mixed>, 1: string}>
	 */
	public static function relationalProvider(): array {
		return [
			'single'    => [ [], 'Type to search' ],
			'multiple'  => [ [ 'multiple' => true ], 'Search to add one or more' ],
			'creatable' => [ [ 'creatable' => true ], 'Search, or type to add' ],
			'both'      => [ [ 'multiple' => true, 'creatable' => true ], 'Search, or type to add' ],
		];
	}

	/**
	 * Every relational type derives one, not just the one tested above.
	 */
	public function test_every_relational_type_derives_one(): void {
		foreach ( [ 'post', 'page', 'user', 'taxonomy', 'ajax' ] as $type ) {
			$this->assertNotNull(
				$this->placeholder( $type, [ 'multiple' => true ] ),
				sprintf( 'A multiple %s control has no placeholder.', $type )
			);
		}
	}

	/**
	 * A configured placeholder wins over the derived one.
	 */
	public function test_a_configured_placeholder_wins(): void {
		$this->assertSame(
			'Pick a country',
			$this->placeholder( 'post', [ 'multiple' => true, 'placeholder' => 'Pick a country' ] )
		);
	}

	/**
	 * A tags input says how to separate tags.
	 *
	 * The token UI mirrors the input rather than replacing it — there is no
	 * Enter key to press — so the separator is the only thing to know, and
	 * nothing on the screen says what it is.
	 */
	public function test_a_tags_input_names_its_separator(): void {
		$this->assertStringContainsString(
			'placeholder="Separate tags with commas"',
			$this->render( 'tags' )
		);

		$this->assertStringContainsString(
			'placeholder="Separate tags with |"',
			$this->render( 'tags', [ 'separator' => '|' ] ),
			'A custom separator is not named.'
		);

		$this->assertStringContainsString(
			'placeholder="Countries"',
			$this->render( 'tags', [ 'placeholder' => 'Countries' ] ),
			'A configured placeholder was overwritten.'
		);
	}

	/**
	 * The script prefers the attribute over the empty option's label.
	 *
	 * The derived text is only reachable if the script reads it, and the
	 * fallback it replaces is still needed for a single local select.
	 */
	public function test_the_script_reads_the_attribute_first(): void {
		$js = (string) file_get_contents( dirname( __DIR__ ) . '/assets/js/field-kit.js' );

		$this->assertMatchesRegularExpression(
			'/placeholder\s*=\s*select\.dataset\.placeholder\s*\|\|/',
			$js,
			'The combobox no longer prefers the configured placeholder.'
		);
	}
}
