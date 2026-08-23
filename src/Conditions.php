<?php
/**
 * Conditional Logic
 *
 * @package     ArrayPress\FieldKit
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit;

/**
 * A field's visibility conditions, in one shape.
 *
 * The predecessor libraries disagreed twice over: post-fields spelled it
 * `show_when` and setting-fields `depends`, and each accepted a different
 * mix of the short map form and the long list form. This accepts every
 * spelling and emits one normalized list, which is also what the script
 * expects — the script previously received whatever the config happened to
 * be, and died on `conditions.forEach is not a function` when that was a map.
 */
final class Conditions {

	/**
	 * Operators the evaluator understands.
	 *
	 * @var string[]
	 */
	private const OPERATORS = [
		'=',
		'!=',
		'>',
		'>=',
		'<',
		'<=',
		'in',
		'not_in',
		'contains',
		'not_contains',
		'empty',
		'not_empty',
	];

	/**
	 * Normalized conditions.
	 *
	 * @var array<int, array{field: string, operator: string, value: mixed}>
	 */
	private array $conditions;

	/**
	 * Construct from already-normalized conditions.
	 *
	 * @param array<int, array{field: string, operator: string, value: mixed}> $conditions Conditions.
	 */
	private function __construct( array $conditions ) {
		$this->conditions = $conditions;
	}

	/**
	 * Build from any of the accepted config shapes.
	 *
	 * Accepts:
	 *   [ 'other_field' => 1 ]                                  short map
	 *   [ 'other_field' => [ 'a', 'b' ] ]                       short map, "in"
	 *   [ [ 'field' => 'x', 'operator' => '>', 'value' => 3 ] ] long list
	 *   [ 'field' => 'x', 'operator' => '>', 'value' => 3 ]     single long
	 *
	 * @param mixed $config Raw config value.
	 *
	 * @return self
	 */
	public static function from( mixed $config ): self {
		if ( ! is_array( $config ) || [] === $config ) {
			return new self( [] );
		}

		// A single long-form condition, not a list of them.
		if ( isset( $config['field'] ) ) {
			$config = [ $config ];
		}

		$conditions = [];

		foreach ( $config as $key => $entry ) {
			if ( is_array( $entry ) && isset( $entry['field'] ) ) {
				$conditions[] = self::normalize(
					(string) $entry['field'],
					$entry['value'] ?? '',
					(string) ( $entry['operator'] ?? '' )
				);
				continue;
			}

			// Short map: key is the field, value is what it must equal.
			if ( is_string( $key ) ) {
				$conditions[] = self::normalize( $key, $entry, '' );
			}
		}

		return new self( array_values( array_filter( $conditions ) ) );
	}

	/**
	 * Normalize one condition.
	 *
	 * @param string $field    Field key the condition watches.
	 * @param mixed  $value    Value to compare against.
	 * @param string $operator Requested operator, may be empty.
	 *
	 * @return array{field: string, operator: string, value: mixed}|null
	 */
	private static function normalize( string $field, mixed $value, string $operator ): ?array {
		if ( '' === $field ) {
			return null;
		}

		if ( '' === $operator ) {
			// An array on the right of a short-form condition means "one of".
			$operator = is_array( $value ) ? 'in' : '=';
		}

		if ( ! in_array( $operator, self::OPERATORS, true ) ) {
			$operator = '=';
		}

		return [
			'field'    => $field,
			'operator' => $operator,
			'value'    => $value,
		];
	}

	/**
	 * Whether there are no conditions.
	 *
	 * @return bool
	 */
	public function is_empty(): bool {
		return [] === $this->conditions;
	}

	/**
	 * The normalized conditions, always a list.
	 *
	 * A list rather than a map matters: this is JSON-encoded for the script,
	 * and a map encodes as a JSON object, which has no `forEach`.
	 *
	 * @return array<int, array{field: string, operator: string, value: mixed}>
	 */
	public function to_array(): array {
		return $this->conditions;
	}

	/**
	 * Evaluate against a set of current values.
	 *
	 * Server-side evaluation matters because a hidden field must not be
	 * saved: the script hides it, but nothing stops a submission carrying it.
	 *
	 * @param array<string, mixed> $values Current values keyed by field.
	 *
	 * @return bool
	 */
	public function are_met( array $values ): bool {
		foreach ( $this->conditions as $condition ) {
			if ( ! $this->is_met( $condition, $values[ $condition['field'] ] ?? null ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Evaluate a single condition.
	 *
	 * Comparisons are deliberately loose. A value that came off an HTML form
	 * is always a string, while config is written in PHP where `1` is an int:
	 * `'1' == 1` has to hold or a condition written the obvious way would
	 * never fire.
	 *
	 * @param array{field: string, operator: string, value: mixed} $condition The condition.
	 * @param mixed                                                $current   Current value.
	 *
	 * @return bool
	 */
	private function is_met( array $condition, mixed $current ): bool {
		$expected = $condition['value'];

		return match ( $condition['operator'] ) {
			'!=' => $current != $expected, // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- see docblock.
			'>' => (float) $current > (float) $expected,
			'>=' => (float) $current >= (float) $expected,
			'<' => (float) $current < (float) $expected,
			'<=' => (float) $current <= (float) $expected,
			'in' => in_array( $current, (array) $expected, false ), // phpcs:ignore WordPress.PHP.StrictInArray.FoundNonStrictFalse -- see docblock.
			'not_in' => ! in_array( $current, (array) $expected, false ), // phpcs:ignore WordPress.PHP.StrictInArray.FoundNonStrictFalse -- see docblock.
			'contains' => is_string( $current ) && '' !== (string) $expected && str_contains( $current, (string) $expected ),
			'not_contains' => ! is_string( $current ) || '' === (string) $expected || ! str_contains( $current, (string) $expected ),
			'empty' => empty( $current ),
			'not_empty' => ! empty( $current ),
			default => $current == $expected, // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- see docblock.
		};
	}
}
