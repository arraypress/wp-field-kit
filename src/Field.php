<?php
/**
 * Normalized Field
 *
 * @package     ArrayPress\FieldKit
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit;

use ArrayPress\FieldKit\Contracts\FieldType;

/**
 * A single field, normalized once and then read-only.
 *
 * Config arrays were passed around raw in the predecessor libraries, so each
 * renderer re-derived the same values with slightly different defaults, and
 * one library's `link` field stored `text` where another stored `title`.
 * Normalizing in one place is what makes those disagreements impossible.
 */
final class Field {

	/**
	 * Field key, unique within its context.
	 *
	 * @var string
	 */
	private string $key;

	/**
	 * The resolved type object.
	 *
	 * @var FieldType
	 */
	private FieldType $type;

	/**
	 * Merged configuration.
	 *
	 * @var array<string, mixed>
	 */
	private array $config;

	/**
	 * Current value.
	 *
	 * @var mixed
	 */
	private mixed $value;

	/**
	 * Construct.
	 *
	 * @param string               $key    Field key.
	 * @param FieldType            $type   Resolved type.
	 * @param array<string, mixed> $config Merged configuration.
	 * @param mixed                $value  Current value.
	 */
	public function __construct( string $key, FieldType $type, array $config, mixed $value = null ) {
		$this->key    = $key;
		$this->type   = $type;
		$this->config = $config;
		$this->value  = $value;
	}

	/**
	 * Get the field key.
	 *
	 * @return string
	 */
	public function key(): string {
		return $this->key;
	}

	/**
	 * Get the resolved type.
	 *
	 * @return FieldType
	 */
	public function type(): FieldType {
		return $this->type;
	}

	/**
	 * Read a config value.
	 *
	 * @param string $key      Config key.
	 * @param mixed  $fallback Returned when absent.
	 *
	 * @return mixed
	 */
	public function get( string $key, mixed $fallback = null ): mixed {
		return $this->config[ $key ] ?? $fallback;
	}

	/**
	 * Whether a config key is set and non-empty.
	 *
	 * @param string $key Config key.
	 *
	 * @return bool
	 */
	public function has( string $key ): bool {
		return isset( $this->config[ $key ] ) && '' !== $this->config[ $key ] && [] !== $this->config[ $key ];
	}

	/**
	 * Get the whole config.
	 *
	 * @return array<string, mixed>
	 */
	public function config(): array {
		return $this->config;
	}

	/**
	 * Get the current value, falling back to the configured default.
	 *
	 * @return mixed
	 */
	public function value(): mixed {
		return null === $this->value || '' === $this->value
			? $this->get( 'default' )
			: $this->value;
	}

	/**
	 * Get the raw value with no default applied.
	 *
	 * @return mixed
	 */
	public function raw_value(): mixed {
		return $this->value;
	}

	/**
	 * Get a copy carrying a different value.
	 *
	 * @param mixed $value New value.
	 *
	 * @return self
	 */
	public function with_value( mixed $value ): self {
		return new self( $this->key, $this->type, $this->config, $value );
	}

	/**
	 * Get a copy with extra config merged in.
	 *
	 * Used by nested types, which render their sub-fields with the parent's
	 * input name prefixed onto each child.
	 *
	 * @param array<string, mixed> $config Config to merge.
	 *
	 * @return self
	 */
	public function with_config( array $config ): self {
		return new self( $this->key, $this->type, array_merge( $this->config, $config ), $this->value );
	}

	/**
	 * The field's human label.
	 *
	 * @return string
	 */
	public function label(): string {
		return (string) $this->get( 'label', '' );
	}

	/**
	 * The field's description.
	 *
	 * @return string
	 */
	public function description(): string {
		return (string) $this->get( 'description', '' );
	}

	/**
	 * The input `name` attribute.
	 *
	 * @return string
	 */
	public function input_name(): string {
		return (string) $this->get( 'input_name', $this->key );
	}

	/**
	 * The input `id` attribute.
	 *
	 * @return string
	 */
	public function input_id(): string {
		return (string) $this->get( 'input_id', sanitize_key( str_replace( [ '[', ']' ], '_', $this->input_name() ) ) );
	}

	/**
	 * Whether the field is required.
	 *
	 * @return bool
	 */
	public function is_required(): bool {
		return (bool) $this->get( 'required', false );
	}

	/**
	 * The field's placeholder, if any.
	 *
	 * @return string
	 */
	public function placeholder(): string {
		return (string) $this->get( 'placeholder', '' );
	}

	/**
	 * Choice options as value => label.
	 *
	 * Accepts a callable so options can be resolved at render time rather
	 * than at registration, which is what dynamic sources need.
	 *
	 * @return array<string, string>
	 */
	public function options(): array {
		$options = $this->get( 'options', [] );

		if ( is_callable( $options ) ) {
			$options = $options( $this );
		}

		return is_array( $options ) ? $options : [];
	}

	/**
	 * Sub-fields for nested types.
	 *
	 * Accepts both `fields` and the legacy `sub_fields` spelling: the two
	 * predecessor libraries disagreed, and config written for either should
	 * keep working.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function sub_fields(): array {
		$fields = $this->get( 'fields', $this->get( 'sub_fields', [] ) );

		return is_array( $fields ) ? $fields : [];
	}
}
