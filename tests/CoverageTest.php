<?php
/**
 * Coverage against the predecessor libraries.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Tests;

use ArrayPress\FieldKit\Registry;
use PHPUnit\Framework\TestCase;

/**
 * The kit only replaces the five wp-register-*-fields libraries if it renders
 * everything they did. This reads their supported types out of their own
 * source and fails on anything the registry cannot resolve — including the
 * spellings that differ between libraries, which resolve through aliases.
 *
 * Skipped rather than failed when a library is not checked out, so the suite
 * still runs in CI where only this repository is present.
 *
 * A library that has been ported has no list left to read: its renderer is
 * this one. Those lists are recorded below as they stood the commit before
 * the port, because the guarantee is about what the kit must still render,
 * and reading it from a file that no longer exists turns a guarantee into a
 * skip — which is how it went unnoticed that the term-fields check had
 * quietly started asserting nothing.
 */
final class CoverageTest extends TestCase {

	/**
	 * Where the sibling libraries live relative to this one.
	 */
	private const LIBRARIES = __DIR__ . '/../..';

	/**
	 * Read a library's supported types from its own source.
	 *
	 * @param string $relative Path under the libraries directory.
	 * @param string $pattern  Regex capturing one type per match.
	 *
	 * @return string[]
	 */
	private function types_from( string $relative, string $pattern ): array {
		$path = self::LIBRARIES . '/' . $relative;

		if ( ! file_exists( $path ) ) {
			$this->markTestSkipped( sprintf( '%s is not checked out.', $relative ) );
		}

		preg_match_all( $pattern, (string) file_get_contents( $path ), $matches );

		$types = [];

		foreach ( $matches[1] as $match ) {
			foreach ( preg_match( "/'/", $match ) ? explode( ',', $match ) : [ $match ] as $one ) {
				$one = trim( $one, " \t\n'" );

				if ( '' !== $one ) {
					$types[] = $one;
				}
			}
		}

		return array_values( array_unique( $types ) );
	}

	/**
	 * Assert the registry resolves every type in a list.
	 *
	 * @param string[] $types   Types the library renders.
	 * @param string   $library Library name for the message.
	 */
	private function assertAllResolve( array $types, string $library ): void {
		$registry = new Registry();
		$missing  = [];

		foreach ( $types as $type ) {
			if ( ! $registry->has( $type ) ) {
				$missing[] = $type;
			}
		}

		$this->assertSame(
			[],
			$missing,
			sprintf(
				"%s renders these types and the kit cannot resolve them: %s",
				$library,
				implode( ', ', $missing )
			)
		);
	}

	/**
	 * Every type wp-register-setting-fields rendered before its port.
	 *
	 * Taken from the match arms of its FieldRenderer trait at 2d8cbc3, the
	 * commit before it was ported onto the kit.
	 *
	 * @var string[]
	 */
	private const SETTING_FIELD_TYPES = [
		'action_button',
		'ajax',
		'button_group',
		'checkbox',
		'checkbox_group',
		'clipboard',
		'code',
		'color',
		'custom',
		'date',
		'datetime',
		'dimensions',
		'email',
		'email_editor',
		'file',
		'gallery',
		'group',
		'heading',
		'hidden',
		'html',
		'image',
		'license',
		'link',
		'message',
		'number',
		'oembed',
		'page',
		'password',
		'post',
		'radio',
		'range',
		'repeater',
		'select',
		'select_multiple',
		'select2',
		'separator',
		'sortable',
		'taxonomy',
		'tel',
		'text',
		'textarea',
		'time',
		'toggle',
		'url',
		'user',
		'wysiwyg',
	];

	/**
	 * Every type wp-register-term-fields rendered before its port.
	 *
	 * Taken from its switch arms at 7a4dee7. Short, because eight types was
	 * the reason for the port.
	 *
	 * @var string[]
	 */
	private const TERM_FIELD_TYPES = [
		'amount_type',
		'checkbox',
		'email',
		'number',
		'select',
		'text',
		'textarea',
		'url',
	];

	/**
	 * Every type wp-register-post-fields rendered before its port.
	 *
	 * Taken from its ConfigParser trait at 0bbd8b9, the commit before it was
	 * ported onto the kit. It includes the spellings that differ between the
	 * predecessor libraries — post_ajax, taxonomy_ajax, user_ajax, term —
	 * which resolve through aliases and are exactly what would break
	 * silently if one were dropped.
	 *
	 * @var string[]
	 */
	private const POST_FIELD_TYPES = [
		'ajax',
		'amount_type',
		'button_group',
		'checkbox',
		'code',
		'color',
		'date',
		'date_range',
		'datetime',
		'dimensions',
		'email',
		'file',
		'file_url',
		'gallery',
		'group',
		'image',
		'link',
		'number',
		'oembed',
		'password',
		'post',
		'post_ajax',
		'radio',
		'range',
		'repeater',
		'select',
		'taxonomy_ajax',
		'tel',
		'term',
		'text',
		'textarea',
		'time',
		'time_range',
		'toggle',
		'url',
		'user',
		'user_ajax',
		'wysiwyg',
	];

	/**
	 * Every setting field type resolves.
	 */
	public function test_setting_fields_types_all_resolve(): void {
		$this->assertAllResolve( self::SETTING_FIELD_TYPES, 'wp-register-setting-fields' );
	}

	/**
	 * Every term field type resolves.
	 */
	public function test_term_fields_types_all_resolve(): void {
		$this->assertAllResolve( self::TERM_FIELD_TYPES, 'wp-register-term-fields' );
	}

	/**
	 * Every post field type resolves.
	 */
	public function test_post_fields_types_all_resolve(): void {
		$this->assertAllResolve( self::POST_FIELD_TYPES, 'wp-register-post-fields' );
	}

	/**
	 * Every flyout form field type resolves.
	 */
	public function test_flyout_types_all_resolve(): void {
		$this->assertAllResolve(
			$this->types_from(
				'wp-register-flyouts/src/Components/FormField.php',
				"/^\s+'([a-z_0-9]+)'\s*=>\s*'render_/m"
			),
			'wp-register-flyouts'
		);
	}

	/**
	 * Every list-table field type resolves.
	 *
	 * These two still switch on a type of their own. A file with no arms left
	 * to read has been ported, so it is asserted to be empty rather than
	 * passed over — an empty list read from a file that still switches would
	 * mean the pattern had stopped matching.
	 */
	public function test_list_table_types_all_resolve(): void {
		$checked = 0;

		foreach (
			[
				'wp-register-quick-edit-fields/src/QuickEditFields.php',
				'wp-register-bulk-edit-fields/src/BulkEditFields.php',
			] as $relative
		) {
			$path = self::LIBRARIES . '/' . $relative;

			if ( ! file_exists( $path ) ) {
				continue;
			}

			++$checked;

			preg_match_all( "/case '([a-z_0-9]+)':/", (string) file_get_contents( $path ), $matches );

			$types = array_diff( $matches[1], [ 'description', 'type', 'label', 'options' ] );

			$this->assertAllResolve( array_values( $types ), $relative );
		}

		if ( 0 === $checked ) {
			$this->markTestSkipped( 'Neither list-table library is checked out.' );
		}
	}

	/**
	 * The types a quick edit or bulk edit row can take.
	 *
	 * Pinned rather than derived, because the list is a set of judgements and
	 * a judgement that changes silently is not one. A new type is not inline
	 * by default, so adding one fails this and has to be argued for.
	 *
	 * @var string[]
	 */
	private const INLINE = [
		'ajax',
		'amount_type',
		'button_group',
		'checkbox',
		'color',
		'date',
		'datetime',
		'email',
		'number',
		'page',
		'password',
		'post',
		'radio',
		'range',
		'select',
		'select2',
		'select_multiple',
		'tags',
		'taxonomy',
		'tel',
		'text',
		'textarea',
		'time',
		'toggle',
		'url',
		'user',
	];

	/**
	 * Exactly these types fit an inline row.
	 *
	 * Quick edit and bulk edit are not smaller edit screens. Quick edit
	 * clones its panel from a hidden template before the values are in it, so
	 * anything started in JavaScript comes up dead in the clone; and both are
	 * one row of a list table, so a panel or a gallery does not fit however
	 * well it works.
	 *
	 * Both libraries used to keep their own whitelist, and they drifted —
	 * which is why the same field worked in one and not the other.
	 */
	public function test_the_inline_types_are_exactly_these(): void {
		$registry = new Registry();
		$inline   = [];

		foreach ( $registry->ids() as $id ) {
			if ( $registry->get( $id )->supports_inline() ) {
				$inline[] = $id;
			}
		}

		sort( $inline );

		$expected = self::INLINE;
		sort( $expected );

		$this->assertSame( $expected, $inline );
	}

	/**
	 * The ones that cannot be inline, and why, one case each.
	 */
	public function test_the_reasons_a_type_is_not_inline(): void {
		$registry = new Registry();

		foreach (
			[
				// Started in JavaScript, and quick edit clones before anything runs.
				'wysiwyg'      => 'needs an editor started in JS',
				'code'         => 'needs an editor started in JS',

				// A panel, a stack of rows or a grid: not one row of a table.
				'email_editor' => 'is a panel',
				'repeater'     => 'is a stack of rows',
				'group'        => 'is a stack of fields',
				'gallery'      => 'is a grid',
				'sortable'     => 'is a reorderable list',

				// Nothing to edit, or nothing that means anything per row.
				'heading'      => 'stores nothing',
				'separator'    => 'stores nothing',
				'clipboard'    => 'is read-only',
				'action_button' => 'runs an action, which a row is not the place for',
				'license'      => 'activates a licence, which a row is not the place for',
				'hidden'       => 'would write the same value to everything selected',
			] as $id => $why
		) {
			$this->assertFalse(
				$registry->get( $id )->supports_inline(),
				sprintf( '%s %s, so it cannot be inline.', $id, $why )
			);
		}
	}

	/**
	 * Every registered type is instantiable and reports the id it is keyed by.
	 */
	public function test_every_registered_type_resolves_to_its_own_id(): void {
		$registry = new Registry();

		foreach ( $registry->ids() as $id ) {
			$this->assertSame(
				$id,
				$registry->get( $id )->id(),
				sprintf( 'Registry key "%s" resolves to a type reporting a different id.', $id )
			);
		}
	}

	/**
	 * Every alias resolves to a real type.
	 */
	public function test_every_alias_resolves(): void {
		$registry = new Registry();

		foreach ( $registry->accepted_ids() as $id ) {
			$this->assertTrue( $registry->has( $id ), sprintf( '"%s" does not resolve.', $id ) );
		}
	}

}
