<?php
/**
 * Stylesheet and markup must agree.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Tests;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * A rule targeting a class the markup never emits is dead code that looks
 * like styling. Nothing raises for it, and it survives every other test.
 *
 * This is not hypothetical. Switching radios and checkbox groups to wrapping
 * labels removed the per-option wrapper element, and the rules that had
 * spaced those options stayed behind pointing at a class nothing rendered any
 * more. The options ran together on one line with no separation between them,
 * and the whole suite still passed.
 */
final class StylesheetTest extends TestCase {

	/**
	 * Classes assembled at runtime rather than written out in full.
	 *
	 * Listed rather than inferred: parsing PHP concatenation from a regex is
	 * its own source of wrong answers, and this list is short and changes
	 * rarely. Each entry names where it is built.
	 *
	 * @var string[]
	 */
	private const BUILT_AT_RUNTIME = [
		// CheckboxGroupType writes this one directly.
		'field-kit__checkbox-group',

		// RepeaterType: 'field-kit__repeater-' . $action
		'field-kit__repeater-move-up',
		'field-kit__repeater-move-down',
		'field-kit__repeater-remove',

		// AbstractType / Renderer: 'field-kit__field--' . $type->id()
		'field-kit__field--conditional',

		// The wrapper carries field-kit__field--{type} for every registered
		// type, built from the id at render time. Only the ones the stylesheet
		// actually targets need listing.
		'field-kit__field--heading',
		'field-kit__field--locked',

		// LicenseType: 'field-kit__license-state--' . ( active ? 'active' : 'inactive' )
		'field-kit__license-state--active',
		'field-kit__license-state--inactive',

		// Modifiers a consumer opts into.
		'field-kit__radio-group--inline',
		'field-kit__checkbox-group--inline',
	];

	/**
	 * Classes a consuming library emits, styled here because it ships no CSS.
	 *
	 * Separate from the list above on purpose. That one is checked back
	 * against this repository's own source; these cannot be, since what
	 * writes them is in another repository. Keeping them apart is what stops
	 * the check on the first list from having to make exceptions — and an
	 * exception is where a genuine mismatch hides.
	 *
	 * @var string[]
	 */
	private const EMITTED_BY_A_CONSUMER = [
		// wp-register-term-fields: a term screen's own row heading.
		'field-kit__row-label',

		// wp-register-quick-edit-fields and wp-register-bulk-edit-fields add
		// this column so core has something to fire their hook against, on a
		// post type whose list table has no custom column of its own. The
		// rule that hides it has to live here, with the stylesheet.
		'column-field-kit-inline',

		// wp-register-setting-fields, wp-register-term-fields and
		// wp-register-user-fields all build the row a heading, separator or
		// notice spans, and each had its own wrapper class. They share this
		// one so a single rule can pull the cell back to the table's edge —
		// core pads it ten pixels in while padding the label cell to nothing,
		// which left every divider indented relative to what it divided.
		'field-kit__spans-row',

		// wp-register-setting-fields: a settings page's export, import and
		// reset controls, which live in the Screen Options panel.
		'field-kit__screen-tools',
		'field-kit__screen-tool',
		'field-kit__screen-tool-title',
		'field-kit__screen-tool-controls',
	];

	/**
	 * Class names the stylesheet targets.
	 *
	 * @return string[]
	 */
	private function stylesheet_classes(): array {
		$css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/field-kit.css' );

		// Comments discuss class names in prose; they are not selectors.
		$css = (string) preg_replace( '#/\*.*?\*/#s', '', $css );

		preg_match_all( '/\.(field-kit__[a-z0-9_-]+)/', $css, $matches );

		return array_values( array_unique( $matches[1] ) );
	}

	/**
	 * Class names written out in the source or the script.
	 *
	 * @return string[]
	 */
	private function emitted_classes(): array {
		$found = [];

		foreach ( [ '/src', '/assets/js' ] as $directory ) {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( dirname( __DIR__ ) . $directory )
			);

			foreach ( $iterator as $file ) {
				if ( ! $file->isFile() || ! in_array( $file->getExtension(), [ 'php', 'js' ], true ) ) {
					continue;
				}

				preg_match_all(
					'/field-kit__[a-z0-9_-]+/',
					(string) file_get_contents( $file->getPathname() ),
					$matches
				);

				$found = array_merge( $found, $matches[0] );
			}
		}

		return array_values(
			array_unique( array_merge( $found, self::BUILT_AT_RUNTIME, self::EMITTED_BY_A_CONSUMER ) )
		);
	}

	/**
	 * A notice on a full-bleed page keeps a gutter on both sides.
	 *
	 * These screens remove #wpcontent's left padding so the header can span
	 * the width, which leaves admin notices -- printed after the header and
	 * outside .wrap -- with nothing supplying one. A horizontal margin of 0
	 * is what that looks like when it is forgotten: the notice runs edge to
	 * edge while the form beneath it sits inset, and it has been forgotten
	 * more than once.
	 */
	public function test_a_full_bleed_notice_is_not_flush_to_the_edges(): void {
		$css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/field-kit.css' );

		// Comments discuss margins in prose.
		$css = (string) preg_replace( '#/\*.*?\*/#s', '', $css );

		preg_match(
			'/\.field-kit__page-screen \.notice,\s*\.field-kit__page-screen \.update-nag\s*\{([^}]*)\}/',
			$css,
			$match
		);

		$this->assertNotEmpty( $match, 'Nothing positions a notice on these screens.' );

		preg_match( '/margin:\s*([^;]+);/', $match[1], $margin );

		$this->assertNotEmpty( $margin, 'The notice rule sets no margin.' );

		$parts = preg_split( '/\s+/', trim( $margin[1] ) );

		// One value is all four sides, two is vertical then horizontal.
		$horizontal = match ( count( $parts ) ) {
			1       => [ $parts[0], $parts[0] ],
			2, 3    => [ $parts[1], $parts[1] ],
			default => [ $parts[1], $parts[3] ],
		};

		foreach ( $horizontal as $side ) {
			$this->assertNotSame( '0', $side, 'A notice is flush against the edge.' );
		}
	}

	/**
	 * Every styled class is one something actually renders.
	 */
	public function test_no_rule_targets_a_class_nothing_emits(): void {
		$dead = array_diff( $this->stylesheet_classes(), $this->emitted_classes() );

		$this->assertSame(
			[],
			array_values( $dead ),
			"These are styled but nothing renders them, so the rules do nothing:\n  "
			. implode( "\n  ", $dead )
		);
	}

	/**
	 * A class listed as built at runtime is really built somewhere.
	 *
	 * The list above is an exemption, and an exemption nobody checks becomes
	 * a place to hide a genuine mismatch.
	 */
	public function test_runtime_classes_have_a_stem_in_source(): void {
		$source = '';

		foreach ( [ '/src', '/assets/js' ] as $directory ) {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( dirname( __DIR__ ) . $directory )
			);

			foreach ( $iterator as $file ) {
				if ( $file->isFile() && in_array( $file->getExtension(), [ 'php', 'js' ], true ) ) {
					$source .= (string) file_get_contents( $file->getPathname() );
				}
			}
		}

		$orphans = [];

		foreach ( self::BUILT_AT_RUNTIME as $class ) {
			// Either written out in full, or some prefix of it appears — the
			// stem it is concatenated onto.
			if ( str_contains( $source, $class ) ) {
				continue;
			}

			$stem  = $class;
			$found = false;

			while ( false !== strrpos( $stem, '-' ) ) {
				$stem = substr( $stem, 0, strrpos( $stem, '-' ) + 1 );

				// Either concatenated onto — 'field-kit__repeater-' . $action —
				// or interpolated by sprintf, which is the same statement
				// written the other way and is how most of the kit builds
				// markup: 'field-kit__license-state--%s'.
				if ( str_contains( $source, "'" . $stem . "'" ) || str_contains( $source, $stem . '%s' ) ) {
					$found = true;
					break;
				}

				$stem = rtrim( $stem, '-' );
			}

			// A modifier is opted into by a consumer's config rather than
			// written by the kit, so there is no stem to find.
			if ( ! $found && ! str_contains( $class, '--inline' ) ) {
				$orphans[] = $class;
			}
		}

		$this->assertSame(
			[],
			$orphans,
			'Exempted as built at runtime, but nothing builds them: ' . implode( ', ', $orphans )
		);
	}


	/**
	 * A row a pointer can choose reacts to the pointer.
	 *
	 * The combobox highlighted for the keyboard — aria-activedescendant, set
	 * as you arrow through — and did nothing at all under the mouse, so a
	 * list of results looked inert to the majority of people using it.
	 */
	public function test_choosable_rows_have_a_hover_state(): void {
		$css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/field-kit.css' );

		foreach ( [ 'field-kit__combobox-option', 'field-kit__tag-item' ] as $class ) {
			preg_match_all( '/\.' . preg_quote( $class, '/' ) . '[^{,]*:hover/', $css, $found );

			// Not the keyboard highlight restated with :hover — that exists
			// so a stray pointer does not repaint the active row, and it
			// matches the same pattern while doing nothing for a pointer on
			// any other row.
			$plain = array_filter(
				$found[0],
				static fn( $selector ) => ! str_contains( $selector, 'aria-selected' )
			);

			$this->assertNotEmpty(
				$plain,
				sprintf( '%s can be clicked but does not react to a pointer.', $class )
			);
		}
	}

	/**
	 * The combobox arrow is painted on the input, not positioned near it.
	 *
	 * It was a separately positioned element inside the wrapper, aligned to
	 * the wrapper rather than to the input — so any rule anywhere that changed
	 * the input's width left the arrow floating out in the margin. Core's
	 * `.form-field input[type="text"] { width: 95% }` is such a rule, so on
	 * every term screen it did, and it was "fixed" three times by arguing
	 * about specificity instead of removing the coupling.
	 *
	 * As a background on the input it is part of the input and cannot come
	 * away from it, whatever sets the width. This asserts the coupling stays
	 * removed.
	 */
	public function test_the_combobox_arrow_is_painted_on_the_input(): void {
		$css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/field-kit.css' );

		// Every rule whose selector names the class — it has more than one,
		// and only one of them carries the arrow.
		preg_match_all( '/([^{}]*field-kit__combobox-input[^{}]*)\{([^}]*)\}/', $css, $rules, PREG_SET_ORDER );

		$this->assertNotEmpty( $rules, 'The combobox input has no rule at all.' );

		$declarations = implode( ' ', array_column( $rules, 2 ) );

		$this->assertStringContainsString(
			'background',
			$declarations,
			'The arrow must be a background on the input, not a separate element.'
		);

		$this->assertStringNotContainsString(
			'field-kit__combobox-toggle',
			$css,
			'A positioned arrow element is back; it will detach from the input again.'
		);

		$js = (string) file_get_contents( dirname( __DIR__ ) . '/assets/js/field-kit.js' );

		$this->assertStringNotContainsString(
			'combobox-toggle',
			$js,
			'The script still builds a separate arrow element.'
		);
	}

	/**
	 * A repeater row's layout cannot reach the table variant's <tr>.
	 *
	 * Both layouts carry .field-kit__repeater-row, because the script
	 * addresses rows by it to reindex and to drag. The stacked layout also
	 * wanted a flex line, and attaching that to the shared class handed it to
	 * every <tr> in the table variant as well — which takes the row out of
	 * the table's column algorithm entirely, so its cells stop lining up
	 * under the headers and the table reads as though it has the wrong number
	 * of columns.
	 *
	 * So a rule that sets `display` on a repeater row has to name the element
	 * the stacked layout renders. Asserted on the selector rather than by
	 * reading the value, because there is no safe value here: `block`,
	 * `grid`, `flow-root` all break a <tr> the same way.
	 */
	public function test_a_repeater_rows_display_cannot_reach_a_table_row(): void {
		$css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/field-kit.css' );

		preg_match_all(
			'/([^{}]*field-kit__repeater-row[^{}]*)\{([^}]*)\}/',
			$css,
			$rules,
			PREG_SET_ORDER
		);

		$this->assertNotEmpty( $rules, 'The repeater row has no rule at all.' );

		$checked = 0;

		foreach ( $rules as $rule ) {
			if ( ! preg_match( '/(^|[;{\s])display\s*:/', $rule[2] ) ) {
				continue;
			}

			foreach ( explode( ',', $rule[1] ) as $selector ) {
				if ( ! str_contains( $selector, 'field-kit__repeater-row' ) ) {
					continue;
				}

				++$checked;

				$this->assertStringContainsString(
					'li.field-kit__repeater-row',
					trim( $selector ),
					'A repeater row is given a display without naming the stacked layout\'s element; '
					. 'the table variant\'s rows will be laid out as flex containers.'
				);
			}
		}

		$this->assertGreaterThan( 0, $checked, 'No rule sets a display on a repeater row; this test proves nothing.' );
	}

	/**
	 * A rule in the admin is two hairlines, and ours have to be the same two.
	 *
	 * core's `hr` is `border-top: 1px solid #dcdcde` over `border-bottom: 1px
	 * solid #f6f7f7`, and that faint second line is what gives every rule in
	 * the admin its slight bevel. A separator that redeclared `border` lost
	 * the lower one and drew a single flat line beside core's bevelled ones;
	 * a heading's underline had never had it at all.
	 *
	 * The heading is not an <hr> and has one bottom edge, so its second line
	 * is a shadow — which is why this asserts on the colours rather than on
	 * which property carries them.
	 */
	public function test_a_rule_carries_both_of_cores_hairlines(): void {
		$css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/field-kit.css' );

		preg_match( '/\.field-kit__separator\s*\{([^}]*)\}/', $css, $separator );

		$this->assertNotEmpty( $separator, 'The separator has no rule.' );

		// The element is an <hr>: core already draws it, and the only way to
		// get this wrong is to draw over it.
		$this->assertDoesNotMatchRegularExpression(
			'/(^|[;\s])border(-top|-bottom)?\s*:/',
			$separator[1],
			'The separator redeclares a border over core\'s, which flattens the bevel.'
		);

		preg_match( '/\.field-kit__heading\s*\{([^}]*)\}/', $css, $heading );

		$this->assertNotEmpty( $heading, 'The heading has no rule.' );

		foreach ( [ '#dcdcde', '#f6f7f7' ] as $colour ) {
			$this->assertStringContainsString(
				$colour,
				$heading[1],
				sprintf( 'A heading\'s rule is missing %s, one of the two lines core draws.', $colour )
			);
		}
	}

	/**
	 * Nothing hidden with the attribute is given a display that beats it.
	 *
	 * The browser's own rule is `[hidden] { display: none }` — a bare element
	 * selector, which any class rule carrying a `display` outranks. The
	 * tooltip panel had one, so a panel rendered `hidden` was permanently on
	 * screen; and the markup is right, so nothing about it looks wrong.
	 *
	 * The kit hides several things this way, because a class-based hide is
	 * still read out by a screen reader and the attribute is not. So every
	 * class that appears alongside `hidden` in the markup has to leave the
	 * attribute able to win.
	 */
	public function test_nothing_hidden_by_attribute_is_displayed_by_a_class(): void {
		$css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/field-kit.css' );

		foreach ( $this->classes_rendered_with_hidden() as $class ) {
			// Any rule that sets a display on the class alone.
			preg_match_all(
				sprintf( '/([^{}]*\.%s(?![\w-])[^{}]*)\{([^}]*)\}/', preg_quote( $class, '/' ) ),
				$css,
				$rules,
				PREG_SET_ORDER
			);

			foreach ( $rules as $rule ) {
				if ( ! preg_match( '/(^|[;{\s])display\s*:/', $rule[2] ) ) {
					continue;
				}

				// Either the rule excludes a hidden element itself, or the
				// stylesheet states the attribute's own rule for this class
				// so it outranks the display.
				$excluded = (bool) preg_match( '/\[hidden\]|:not\(\s*\[hidden\]\s*\)/', $rule[1] );
				$restated = (bool) preg_match(
					sprintf( '/\.%s\[hidden\][^{}]*\{[^}]*display\s*:\s*none/', preg_quote( $class, '/' ) ),
					$css
				);

				$this->assertTrue(
					$excluded || $restated,
					sprintf(
						'.%s is given a display and is rendered carrying the hidden attribute. '
						. 'The browser\'s [hidden] rule is a bare element selector and loses to a '
						. 'class rule, so the element is permanently visible. Drop the display, or '
						. 'state .%s[hidden] { display: none }.',
						$class,
						$class
					)
				);
			}
		}
	}

	/**
	 * Classes the kit renders on an element carrying `hidden`.
	 *
	 * Read out of the source rather than listed, so a new one is covered
	 * without anyone remembering to add it here.
	 *
	 * @return string[]
	 */
	private function classes_rendered_with_hidden(): array {
		$classes = [];

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( dirname( __DIR__ ) . '/src' )
		);

		foreach ( $iterator as $file ) {
			if ( ! $file->isFile() || 'php' !== $file->getExtension() ) {
				continue;
			}

			$source = (string) file_get_contents( $file->getPathname() );

			// A class attribute and a bare `hidden` in the same tag.
			preg_match_all( '/class="([^"]*field-kit__[^"]*)"[^>]*\shidden/', $source, $matches );

			foreach ( $matches[1] as $attribute ) {
				foreach ( preg_split( '/\s+/', $attribute ) as $class ) {
					if ( str_starts_with( $class, 'field-kit__' ) ) {
						$classes[] = $class;
					}
				}
			}
		}

		return array_values( array_unique( $classes ) );
	}

	/**
	 * No rule swallows the one above it.
	 *
	 * A comment between a comma and the opening brace merges two rules: the
	 * selectors before it keep matching, but they take the *next* rule's
	 * declarations. That is what happened when a comment was inserted above
	 * `.field-kit__oembed-card` — a dimensions field and a range pair
	 * inherited its border, padding and white background, and appeared as
	 * boxes nobody had asked for.
	 *
	 * CSS has no errors, so nothing said a word.
	 */
	public function test_no_comment_sits_inside_a_selector_list(): void {
		$css   = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/field-kit.css' );
		$merged = [];

		preg_match_all( '/(?:^|\})([^{}]*)\{/', $css, $rules );

		foreach ( $rules[1] as $selector ) {
			$before = explode( '/*', $selector )[0];

			if ( str_contains( $selector, '/*' ) && str_contains( $before, ',' ) ) {
				$merged[] = trim( explode( "\n", trim( $before ) )[0] );
			}
		}

		$this->assertSame(
			[],
			$merged,
			"A comment sits between a comma and a brace, so these selectors take the next rule's declarations:\n  "
			. implode( "\n  ", $merged )
		);
	}

	/**
	 * Every brace is closed.
	 *
	 * One missing takes every rule after it with it, silently.
	 */
	public function test_the_braces_balance(): void {
		$css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/field-kit.css' );

		$this->assertSame( substr_count( $css, '{' ), substr_count( $css, '}' ) );
	}
	/**
	 * No selector is given `display` twice.
	 *
	 * A later rule wins silently, and the two are usually a long way apart —
	 * the clipboard was `display: flex` at line 1495 and `display: flow-root`
	 * at 1628, so its copy confirmation sat on the baseline beside the button
	 * instead of centred with it, and nothing anywhere said why.
	 */
	public function test_no_selector_is_given_display_twice(): void {
		$css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/field-kit.css' );

		// Comments out of the way, so one mentioning `display` is not a rule.
		$css = (string) preg_replace( '{/\*.*?\*/}s', '', $css );

		$seen   = [];
		$twice  = [];

		preg_match_all( '/(?:^|\})([^{}@]*)\{([^}]*)\}/', $css, $rules, PREG_SET_ORDER );

		foreach ( $rules as $rule ) {
			if ( ! preg_match( '/(?:^|;|\s)display\s*:/', $rule[2] ) ) {
				continue;
			}

			foreach ( explode( ',', $rule[1] ) as $selector ) {
				$selector = trim( (string) preg_replace( '/\s+/', ' ', $selector ) );

				if ( '' === $selector ) {
					continue;
				}

				if ( isset( $seen[ $selector ] ) ) {
					$twice[] = $selector;
				}

				$seen[ $selector ] = true;
			}
		}

		// A modifier legitimately restates it — `:not(.is-active)` hiding
		// something the base rule shows — so only a bare repeat counts.
		$twice = array_values(
			array_filter(
				array_unique( $twice ),
				static fn( $selector ) => ! str_contains( $selector, ':' ) && ! str_contains( $selector, '[' )
			)
		);

		$this->assertSame(
			[],
			$twice,
			"These are given display twice, and the later one wins:\n  " . implode( "\n  ", $twice )
		);
	}
	/**
	 * The header's actions slot is a row.
	 *
	 * It was `text-align: center`, which centres inline content and does
	 * nothing whatever to a block — so a consumer putting two controls in the
	 * slot got them stacked one above the other, the second hard against the
	 * left. The reports screen had a refresh button sitting on top of its
	 * date picker for exactly that reason, and every rule involved was doing
	 * what it said.
	 */
	public function test_the_page_actions_slot_lays_its_controls_out_in_a_row(): void {
		$css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/field-kit.css' );

		preg_match( '/\n\.field-kit__page-actions\s*\{([^}]*)\}/', $css, $rule );

		$this->assertNotEmpty( $rule, 'The actions slot has no rule.' );
		$this->assertStringContainsString( 'display: flex', $rule[1] );
		$this->assertStringNotContainsString( 'text-align: center', $rule[1] );
	}

	/**
	 * A range is capped on a settings page and nowhere else.
	 *
	 * Both halves, because each one alone was wrong at some point. A settings
	 * page cell is as wide as the screen, so an uncapped slider runs the
	 * width of a monitor to pick a number between one and ten and reads as a
	 * progress bar. A term or user screen cell is already narrow, and capping
	 * it there makes the slider stop short of the field beside it.
	 */
	public function test_a_range_is_capped_only_on_a_settings_page(): void {
		$css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/field-kit.css' );

		preg_match( '/\n\.field-kit__range\s*\{([^}]*)\}/', $css, $bare );

		$this->assertNotEmpty( $bare, 'The range has no rule.' );
		$this->assertStringNotContainsString( 'max-width', $bare[1] );

		preg_match( '/\.field-kit__page-screen \.field-kit__range\s*\{([^}]*)\}/', $css, $scoped );

		$this->assertNotEmpty( $scoped, 'Nothing caps the range on a settings page.' );
		$this->assertStringContainsString( 'max-width', $scoped[1] );
	}

}