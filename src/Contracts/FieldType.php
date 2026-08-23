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
	 * Script and style handles this type needs.
	 *
	 * @return array{scripts: string[], styles: string[]}
	 */
	public function dependencies(): array;
}
