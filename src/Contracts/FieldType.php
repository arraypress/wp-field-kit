<?php
/**
 * Field Type Contract
 *
 * @package     ArrayPress\FieldKit
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Contracts;

use ArrayPress\FieldKit\Attributes;
use ArrayPress\FieldKit\Field;

/**
 * One class per field type.
 *
 * A type renders and sanitizes *the control only*. The label, description,
 * required marker and every accessibility association around it belong to the
 * renderer, so no type can forget them — which is how the five predecessor
 * libraries ended up with inconsistent labelling.
 */
interface FieldType {

	/**
	 * The type's identifier, as written in field config.
	 *
	 * @return string
	 */
	public function id(): string;

	/**
	 * Config defaults merged under a field of this type.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array;

	/**
	 * Render the control.
	 *
	 * @param Field      $field      The normalized field.
	 * @param Attributes $attributes Attributes the renderer has prepared,
	 *                               already carrying id, name and every
	 *                               accessibility association.
	 *
	 * @return string
	 */
	public function render( Field $field, Attributes $attributes ): string;

	/**
	 * Coerce a submitted value into what should be stored.
	 *
	 * @param mixed $value Raw submitted value.
	 * @param Field $field The normalized field.
	 *
	 * @return mixed
	 */
	public function sanitize( mixed $value, Field $field ): mixed;

	/**
	 * Whether a `placeholder` is meaningful for this type.
	 *
	 * @return bool
	 */
	public function supports_placeholder(): bool;

	/**
	 * Whether the control can live in a quick edit or a bulk edit row.
	 *
	 * Those two screens are not simply smaller versions of an edit screen.
	 * Quick edit clones its panel from a hidden template *before* the values
	 * are in it, so anything that has to be started in JavaScript — TinyMCE,
	 * CodeMirror — comes up dead in the clone. Both are one row of a list
	 * table, so anything that is a panel, a gallery or a stack of rows does
	 * not fit however well it works.
	 *
	 * The default is no. A type says it can, rather than every list-table
	 * library keeping its own whitelist and each one drifting differently —
	 * which is what they did, and why the same field worked in one and not
	 * the other.
	 *
	 * @return bool
	 */
	public function supports_inline(): bool;

	/**
	 * Whether this type stores a value at all.
	 *
	 * Layout types — heading, separator, message, html — render and store
	 * nothing. Saying so here keeps that knowledge out of every save path.
	 *
	 * @return bool
	 */
	public function stores_value(): bool;

	/**
	 * Whether the control labels itself.
	 *
	 * A checkbox puts its own text beside the box, so the renderer must not
	 * also emit a `<label>` above it.
	 *
	 * @return bool
	 */
	public function is_self_labelling(): bool;

	/**
	 * Whether the control is a group of inputs rather than a single one.
	 *
	 * Radios and checkbox groups have no single element to point a `<label
	 * for>` at, so the renderer wraps them in a fieldset with a legend.
	 *
	 * @return bool
	 */
	public function is_grouped(): bool;

	/**
	 * Whether the field wants the whole row rather than a cell beside a label.
	 *
	 * A caller laying fields out in a table asks this rather than inferring
	 * it from `stores_value()`. The two coincided for a while — only layout
	 * types spanned — and then the email editor became a panel, which stores
	 * a value and still cannot sit in a cell built for one control.
	 *
	 * @return bool
	 */
	public function spans_row(): bool;

	/**
	 * The shape this type stores, as a JSON Schema fragment.
	 *
	 * Asked of the type rather than mapped in a table somewhere, because the
	 * type is the only thing that knows what its own `sanitize()` returns —
	 * and a schema that disagrees with what is stored is worse than none:
	 * WordPress rejects valid values against it, and only over REST, so it
	 * works in the admin and fails everywhere else.
	 *
	 * @param Field $field The field, since the shape can depend on its config.
	 *
	 * @return array<string, mixed>
	 */
	public function schema( Field $field ): array;

	/**
	 * Script and style handles this type needs.
	 *
	 * @return array{scripts: string[], styles: string[]}
	 */
	public function dependencies(): array;
}
