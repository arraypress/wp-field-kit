<?php
/**
 * Code Field Type
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Attributes;
use ArrayPress\FieldKit\Field;

/**
 * A code editor, backed by CodeMirror.
 *
 * The underlying control stays a textarea, so the field still works — and
 * stays keyboard accessible — if the editor script fails to load.
 */
final class CodeType extends TextareaType {

	/**
	 * Config defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return [
			'rows'     => 10,
			'language' => 'text/html',
		];
	}

	/**
	 * Render the editor.
	 *
	 * @param Field      $field      The field.
	 * @param Attributes $attributes Prepared attributes.
	 *
	 * @return string
	 */
	public function render( Field $field, Attributes $attributes ): string {
		$attributes->add_class( 'field-kit__code' );
		$attributes->set( 'data-language', (string) $field->get( 'language', 'text/html' ) );

		return parent::render( $field, $attributes );
	}

	/**
	 * Coerce a submitted value.
	 *
	 * Code is stored verbatim apart from slashes: running it through a
	 * sanitizer would rewrite the very characters it exists to hold. The
	 * capability check on the screen is the real gate, exactly as it is for
	 * the theme and plugin editors in core.
	 *
	 * @param mixed $value Raw submitted value.
	 * @param Field $field The field.
	 *
	 * @return string
	 */
	public function sanitize( mixed $value, Field $field ): string {
		return (string) $value;
	}

	/**
	 * What this type needs enqueued.
	 *
	 * The editor is not a plain script handle: wp_enqueue_code_editor()
	 * enqueues CodeMirror plus the mode and linter for a given type, and
	 * returns the settings its initialiser needs. Naming the type here lets
	 * the asset registrar make that call once per language actually used.
	 *
	 * @return array{scripts: string[], styles: string[], code_editors: string[]}
	 */
	public function dependencies(): array {
		return [
			'scripts'      => [],
			'styles'       => [],
			'code_editors' => [ 'text/html' ],
		];
	}

	/**
	 * The editor languages this field uses.
	 *
	 * @param \ArrayPress\FieldKit\Field $field The field.
	 *
	 * @return string[]
	 */
	public function editor_types( $field ): array {
		return [ (string) $field->get( 'language', 'text/html' ) ];
	}

	/**
	 * Does not fit an inline row.
	 *
	 * CodeMirror has to be started in JavaScript, and quick edit clones its panel before anything runs in it — so the editor comes up as a bare textarea, or not at all.
	 *
	 * @return bool
	 */
	public function supports_inline(): bool {
		return false;
	}
}
