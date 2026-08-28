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
	 * Configuration every field understands, whatever its type.
	 *
	 * Read by Field itself, by the renderer, by the field set and by the meta
	 * registrar rather than by any one type — which is why they are listed
	 * here and not repeated across fifty-odd type classes.
	 *
	 * @var string[]
	 */
	public const COMMON_KEYS = [
		// Identity and rendering.
		'type',
		'label',
		'description',
		'default',
		'placeholder',
		'required',
		'options',
		'fields',
		'sub_fields',
		'class',
		'width',

		// Set by a repeater on every child it builds rather than by a
		// caller, so it is common whether or not the type in the row reads
		// it. Declared on FileType alone, it warned on each of the other
		// dozen types a row can hold: a product panel of three price tiers
		// reported a hundred and thirty keys nothing reads, all of them put
		// there by the kit itself.
		'inline',
		'data',
		'input_id',
		'input_name',
		'disabled',
		'readonly',
		'autocomplete',

		// Conditional display. `depends` is the older spelling of `show_when`
		// and both are read, so both are valid.
		'show_when',
		'depends',

		// Handled outside the type: search endpoints, row actions, badges,
		// storage and REST exposure.
		'search_source',
		'search_callback',
		'search_capability',
		'actions',
		'action_names',
		'action_callback',
		'action_capability',
		'badge',
		'tooltip',
		'encrypted',
		'show_in_rest',
		'capability',
		'meta_key',
		'sanitize_callback',

		// Consumed by a consuming library rather than by the kit: a table
		// repeater's column width, a metabox panel's placement.
		'column_width',
		'panel',
	];

	/**
	 * Keys a consuming library reads for itself.
	 *
	 * The kit cannot know these. A term field's `permission_callback`, a user
	 * field's `own_capability`, a table repeater's `column_width` — each is
	 * read by the library that renders the surface, not by any type here, and
	 * without somewhere to declare them the check below would report every
	 * one of them as a mistake.
	 *
	 * @var string[]
	 */
	private static array $allowed = [];

	/**
	 * Declare configuration a consuming library reads.
	 *
	 * Called once as a library boots.
	 *
	 * @param string[] $keys Configuration keys.
	 *
	 * @return void
	 */
	public static function allow_config_keys( array $keys ): void {
		self::$allowed = array_values( array_unique( array_merge( self::$allowed, $keys ) ) );
	}

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

		$this->warn_about_unknown_keys();
		$this->warn_about_missing_options();
	}

	/**
	 * Configuration keys nothing will read.
	 *
	 * A key nothing reads is not an error in PHP — the entry sits in the
	 * array, the control renders with its defaults, and the only symptom is
	 * that a documented option quietly does nothing. Every one of these has
	 * happened: `button_label` on a repeater, whose add button is `add_label`;
	 * `max_items` on a repeater, whose limit is `max_rows`; a multiple select
	 * given `display => checkbox`, which is its own type.
	 *
	 * @return string[]
	 */
	public function unknown_keys(): array {
		return array_values(
			array_diff(
				array_keys( $this->config ),
				self::COMMON_KEYS,
				self::$allowed,
				$this->type->config_keys()
			)
		);
	}

	/**
	 * Complain, under WP_DEBUG, about a choice with nothing to choose from.
	 *
	 * A select with no options renders an empty dropdown and a card choice
	 * renders an empty box. Neither throws, so the only symptom is a field
	 * that looks like it failed to load — which is what a card choice did in
	 * this library's own demo, on the page whose one job is showing what each
	 * type looks like.
	 *
	 * The options may be a callable, and one that legitimately returns
	 * nothing — no products yet, no terms yet — is not a mistake. It is still
	 * worth saying: an empty control with no explanation looks the same
	 * either way, and a field that expects to be empty sometimes should say
	 * so with `empty_label`.
	 *
	 * @return void
	 */
	private function warn_about_missing_options(): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG || ! function_exists( '_doing_it_wrong' ) ) {
			return;
		}

		// Only the types that read them. A text field given none is not a
		// choice with nothing in it.
		if ( ! in_array( 'options', $this->type->config_keys(), true ) ) {
			return;
		}

		// A relational type fills its own from a search, so an empty
		// configuration is the normal case rather than an omission.
		if ( $this->has( 'search_callback' ) || $this->has( 'search_source' ) ) {
			return;
		}

		if ( [] !== $this->options() ) {
			return;
		}

		_doing_it_wrong(
			__METHOD__,
			esc_html(
				sprintf(
					/* translators: 1: field key, 2: field type */
					__( 'The field "%1$s" (%2$s) has no options, so it renders with nothing to choose from.', 'arraypress' ),
					$this->key,
					$this->type->id()
				)
			),
			'1.0.0'
		);
	}

	/**
	 * Say so, while someone is looking.
	 *
	 * Debug only: this is a mistake in a consumer's configuration, not a
	 * reason to interrupt a live site, and a field that ignores a key it does
	 * not know still renders.
	 *
	 * @return void
	 */
	private function warn_about_unknown_keys(): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG || ! function_exists( '_doing_it_wrong' ) ) {
			return;
		}

		$unknown = $this->unknown_keys();

		if ( [] === $unknown ) {
			return;
		}

		_doing_it_wrong(
			__METHOD__,
			esc_html(
				sprintf(
					/* translators: 1: field key, 2: field type, 3: comma-separated list of configuration keys */
					__( 'The field "%1$s" (%2$s) was given configuration nothing reads: %3$s.', 'arraypress' ),
					$this->key,
					$this->type->id(),
					implode( ', ', $unknown )
				)
			),
			'1.0.0'
		);
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
