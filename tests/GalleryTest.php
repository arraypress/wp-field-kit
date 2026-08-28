<?php
/**
 * Gallery limit tests.
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
 * A gallery's cap is the sort of rule that gets written into the script and
 * nowhere else, which makes it a suggestion. The value posts as a plain
 * comma-separated list of ids in an ordinary hidden input, so anything that
 * can post a form can post thirty of them to a field that says ten.
 *
 * So the cap is asserted where it has to hold: on the way in.
 */
final class GalleryTest extends TestCase {

	/**
	 * Build a gallery field.
	 *
	 * @param array<string, mixed> $config Extra configuration.
	 *
	 * @return Field
	 */
	private function field( array $config = [] ): Field {
		return new Field(
			'photos',
			( new Registry() )->get( 'gallery' ),
			array_merge( [ 'label' => 'Photos', 'input_name' => 'photos' ], $config ),
			null
		);
	}

	/**
	 * A submission longer than the cap is cut to it.
	 */
	public function test_a_submission_is_cut_to_the_limit(): void {
		$field = $this->field( [ 'max_items' => 3 ] );

		$this->assertSame(
			[ 1, 2, 3 ],
			$field->type()->sanitize( '1,2,3,4,5', $field )
		);
	}

	/**
	 * An array posts the same way a string does.
	 */
	public function test_an_array_is_cut_the_same_way(): void {
		$field = $this->field( [ 'max_items' => 2 ] );

		$this->assertSame(
			[ 7, 8 ],
			$field->type()->sanitize( [ 7, 8, 9 ], $field )
		);
	}

	/**
	 * No cap means no cap, not a default one.
	 */
	public function test_without_a_limit_everything_is_kept(): void {
		$field = $this->field();

		$this->assertCount( 5, $field->type()->sanitize( '1,2,3,4,5', $field ) );
	}

	/**
	 * Empty and non-numeric entries are dropped either way.
	 */
	public function test_rubbish_is_dropped_before_the_limit_applies(): void {
		$field = $this->field( [ 'max_items' => 3 ] );

		// Were the filtering done after slicing, this would return two ids.
		$this->assertSame(
			[ 4, 5, 6 ],
			$field->type()->sanitize( '0,abc,4,5,6,7', $field )
		);
	}

	/**
	 * The limit reaches the script, so the button can disable itself.
	 */
	public function test_the_limit_reaches_the_markup(): void {
		$html = ( new Renderer() )->render( $this->field( [ 'max_items' => 4 ] ) );

		$this->assertStringContainsString( 'data-max-items="4"', $html );
	}

	/**
	 * And the script actually reads it, on both paths that need it.
	 *
	 * The frame is a multi-select, so the check cannot happen once before it
	 * opens — each attachment arrives separately.
	 */
	public function test_the_script_enforces_it(): void {
		$js = (string) file_get_contents( dirname( __DIR__ ) . '/assets/js/field-kit.js' );

		$this->assertSame(
			2,
			substr_count( $js, 'wrap.dataset.maxItems' ),
			'The gallery limit must be checked as items arrive and again when the list settles.'
		);
	}

	/**
	 * A preview you can play, since checking it is the point.
	 *
	 * A filename says which row is which and nothing else.
	 *
	 * @return void
	 */
	public function test_audio_gets_a_player(): void {
		$html = $this->with_attachment( 'audio/mpeg', 'demo.mp3' );

		$this->assertStringContainsString( '<audio', $html );
		$this->assertStringContainsString( 'controls', $html );

		// Or a panel of a dozen previews fetches a dozen files before
		// anybody presses anything.
		$this->assertStringContainsString( 'preload="none"', $html );
	}

	/**
	 * Anything with nothing to play gets the icon for its type.
	 *
	 * @return void
	 */
	public function test_a_pdf_gets_its_icon_and_its_name(): void {
		$html = $this->with_attachment( 'application/pdf', 'sleeve-notes.pdf' );

		$this->assertStringContainsString( 'dashicons-pdf', $html );
		$this->assertStringContainsString( 'sleeve-notes.pdf', $html );
		$this->assertStringNotContainsString( '<audio', $html );
	}

	/**
	 * A gallery that is not of images does not say "images" on its button.
	 *
	 * @return void
	 */
	public function test_the_wording_follows_the_mime_type(): void {
		$html = $this->with_attachment( 'audio/mpeg', 'demo.mp3' );

		$this->assertStringContainsString( 'Add files', $html );
		$this->assertStringNotContainsString( 'Add images', $html );
		$this->assertStringContainsString( 'data-frame-title="Choose files"', $html );
	}

	/**
	 * And a caller can say exactly what it wants instead.
	 *
	 * @return void
	 */
	public function test_the_wording_can_be_given_outright(): void {
		$html = $this->with_attachment(
			'audio/mpeg',
			'demo.mp3',
			[ 'add_label' => 'Add a preview', 'frame_title' => 'Choose previews' ]
		);

		$this->assertStringContainsString( 'Add a preview', $html );
		$this->assertStringContainsString( 'data-frame-title="Choose previews"', $html );
	}

	/**
	 * A consumer with something better to show gets the item body.
	 *
	 * The only way this can support a waveform player or a PDF thumbnailer
	 * without the kit taking a dependency on either.
	 *
	 * @return void
	 */
	public function test_a_callback_replaces_the_preview(): void {
		$html = $this->with_attachment(
			'audio/mpeg',
			'demo.mp3',
			[ 'preview_callback' => static fn( int $id ): string => '<div class="waveform" data-id="' . $id . '"></div>' ]
		);

		$this->assertStringContainsString( 'class="waveform"', $html );
		$this->assertStringNotContainsString( '<audio', $html );
	}

	/**
	 * A callback that returns nothing falls back rather than drawing a hole.
	 *
	 * @return void
	 */
	public function test_an_empty_callback_falls_back(): void {
		$html = $this->with_attachment(
			'audio/mpeg',
			'demo.mp3',
			[ 'preview_callback' => static fn (): string => '' ]
		);

		$this->assertStringContainsString( '<audio', $html );
	}

	/**
	 * Render a gallery holding one attachment of a given type.
	 *
	 * @param string               $mime   Its mime type.
	 * @param string               $name   Its filename.
	 * @param array<string, mixed> $config Extra field configuration.
	 *
	 * @return string
	 */
	private function with_attachment( string $mime, string $name, array $config = [] ): string {
		$GLOBALS['fk_attachments'] = [
			7 => [
				'mime' => $mime,
				'url'  => 'https://example.test/' . $name,
				'name' => $name,
			],
		];

		$field = new Field(
			'previews',
			( new Registry() )->get( 'gallery' ),
			array_merge(
				[ 'label' => 'Previews', 'input_name' => 'previews', 'mime_type' => 'audio' ],
				$config
			),
			null
		);

		$html = ( new Renderer() )->render( $field->with_value( [ 7 ] ) );

		unset( $GLOBALS['fk_attachments'] );

		return $html;
	}
}
