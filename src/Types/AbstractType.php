<?php
/**
 * Base Field Type
 *
 * @package     ArrayPress\FieldKit
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Attributes;
use ArrayPress\FieldKit\Contracts\FieldType;
use ArrayPress\FieldKit\Field;
use ArrayPress\FieldKit\Registry;

/**
 * Defaults every type inherits.
 *
 * Subclasses override only what differs, so a new type is a handful of lines
 * rather than a copy of an existing one — which is how the predecessor
 * libraries drifted into six sanitizers of the same name with six different
 * bodies.
 */
abstract class AbstractType implements FieldType {

	/**
	 * The registry that produced this type.
	 *
	 * Nested types need it to resolve their children. Passing it in rather
	 * than constructing one means a consumer's custom types are visible to
	 * fields nested inside a repeater, which they would not be otherwise.
	 *
	 * @var Registry|null
	 */
	protected ?Registry $registry;

	/**
	 * Construct.
	 *
	 * @param Registry|null $registry The registry that produced this type.
	 */
	public function __construct( ?Registry $registry = null ) {
		$this->registry = $registry;
	}

	/**
	 * Render the control.
	 *
	 * @param Field      $field      The field.
	 * @param Attributes $attributes Prepared attributes.
	 *
	 * @return string
	 */
	abstract public function render( Field $field, Attributes $attributes ): string;

	/**
	 * The type's identifier, derived from the class name.
	 *
	 * `SelectMultipleType` becomes `select_multiple`. Overriding is only
	 * needed where the id does not follow from the name.
	 *
	 * @return string
	 */
	public function id(): string {
		$short = substr( static::class, strrpos( static::class, '\\' ) + 1 );
		$short = preg_replace( '/Type$/', '', $short ) ?? $short;

		return strtolower( ltrim( (string) preg_replace( '/([A-Z])/', '_$1', $short ), '_' ) );
	}

	/**
	 * Config defaults for this type.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return [];
	}

	/**
	 * Coerce a submitted value.
	 *
	 * Text is the safe default: a type that stores something structured says
	 * so explicitly rather than inheriting a guess.
	 *
	 * @param mixed $value Raw submitted value.
	 * @param Field $field The field.
	 *
	 * @return mixed
	 */
	public function sanitize( mixed $value, Field $field ): mixed {
		return sanitize_text_field( (string) $value );
	}

	/**
	 * Whether a placeholder is meaningful.
	 *
	 * @return bool
	 */
	public function supports_placeholder(): bool {
		return false;
	}

	/**
	 * Whether the type stores a value.
	 *
	 * @return bool
	 */
	public function stores_value(): bool {
		return true;
	}

	/**
	 * Whether the control labels itself.
	 *
	 * @return bool
	 */
	public function is_self_labelling(): bool {
		return false;
	}

	/**
	 * Whether the control is a group of inputs.
	 *
	 * @return bool
	 */
	public function is_grouped(): bool {
		return false;
	}

	/**
	 * Whether the field wants the whole row.
	 *
	 * False here, and true only where a type says so. It used to be derived
	 * from `stores_value()`, which conflated two different questions: a
	 * clipboard and an action button store nothing and are still controls
	 * with a name — and they lost their label entirely, because the table
	 * gave them a spanning cell with no header.
	 *
	 * @return bool
	 */
	public function spans_row(): bool {
		return false;
	}

	/**
	 * The shape this type stores.
	 *
	 * A string, which is what most controls sanitize to. Anything else says
	 * so for itself.
	 *
	 * @param Field $field The field.
	 *
	 * @return array<string, mixed>
	 */
	public function schema( Field $field ): array {
		return [ 'type' => 'string' ];
	}

	/**
	 * Script and style handles this type needs.
	 *
	 * @return array{scripts: string[], styles: string[]}
	 */
	public function dependencies(): array {
		return [
			'scripts' => [],
			'styles'  => [],
		];
	}

	/**
	 * Render a `<label>` for one of several inputs the type emits itself.
	 *
	 * Types that draw more than one control — dimensions, link, date range —
	 * still owe each one a programmatically associated label. This is the
	 * only sanctioned way to write one, so none of them can quietly ship a
	 * bare input.
	 *
	 * @param string $target   Target input id.
	 * @param string $text  Label text.
	 * @param bool   $visible Whether the label is visible or screen-reader only.
	 *
	 * @return string
	 */
	protected function sub_label( string $target, string $text, bool $visible = true ): string {
		return sprintf(
			'<label class="field-kit__sub-label%s" for="%s">%s</label>',
			$visible ? '' : ' screen-reader-text',
			esc_attr( $target ),
			esc_html( $text )
		);
	}

	/**
	 * Build attributes for one of several inputs the type emits itself.
	 *
	 * Derives a distinct id and name per part and carries the parent's
	 * accessibility associations onto the first part, so a description still
	 * announces once rather than once per input.
	 *
	 * @param Attributes $source Attributes prepared by the renderer.
	 * @param string     $part   Part suffix, e.g. "width".
	 * @param bool       $first  Whether this is the part the label points at.
	 *
	 * @return Attributes
	 */
	protected function part_attributes( Attributes $source, string $part, bool $first = false ): Attributes {
		$attributes = new Attributes();

		$id   = (string) $source->get( 'id', '' );
		$name = (string) $source->get( 'name', '' );

		$attributes->set( 'id', $id . '_' . $part );
		$attributes->set( 'name', $name . '[' . $part . ']' );

		if ( $first && $source->has( 'aria-describedby' ) ) {
			$attributes->set( 'aria-describedby', $source->get( 'aria-describedby' ) );
		}

		if ( $source->has( 'disabled' ) ) {
			$attributes->set( 'disabled', $source->get( 'disabled' ) );
		}

		return $attributes;
	}

	/**
	 * Whether the control can live in a quick edit or a bulk edit row.
	 *
	 * No, unless a type says otherwise. A new type appearing in quick edit
	 * and breaking is worse than one not appearing until someone asks for it.
	 *
	 * @return bool
	 */
	public function supports_inline(): bool {
		return false;
	}
}
