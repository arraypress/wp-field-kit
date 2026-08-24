<?php
/**
 * Base Nested Type
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Field;
use ArrayPress\FieldKit\Registry;
use ArrayPress\FieldKit\Renderer;

/**
 * Types that render other fields inside themselves.
 *
 * Children go through the same registry and the same renderer as any other
 * field, so a field nested three deep gets identical markup, identical
 * sanitizing and identical accessibility to one at the top level. The
 * predecessor libraries re-implemented a cut-down renderer for nested
 * fields, which is why a group rendered as a single empty text input when
 * its config used `fields` rather than `sub_fields`.
 */
abstract class AbstractNestedType extends AbstractType {

	/**
	 * Build a child field.
	 *
	 * @param Field                $owner  Parent field.
	 * @param string               $key    Child key.
	 * @param array<string, mixed> $config Child config.
	 * @param mixed                $value  Child value.
	 * @param string               $prefix Input-name prefix for the child.
	 * @param string               $scope  Extra id discriminator, such as a
	 *                                     repeater row index. Ids must be
	 *                                     unique across the document: a
	 *                                     duplicate silently breaks every
	 *                                     label association below the first.
	 *
	 * @return Field|null
	 */
	protected function child( Field $owner, string $key, array $config, mixed $value, string $prefix, string $scope = '' ): ?Field {
		$registry = $this->registry ?? new Registry();
		$type     = (string) ( $config['type'] ?? 'text' );

		if ( ! $registry->has( $type ) ) {
			return null;
		}

		$resolved = $registry->get( $type );

		$config = array_merge(
			$resolved->defaults(),
			$config,
			[
				'input_name' => $prefix . '[' . $key . ']',
				'input_id'   => sanitize_key( $owner->input_id() . ( '' === $scope ? '' : '_' . $scope ) . '_' . $key ),
			]
		);

		return new Field( $key, $resolved, $config, $value );
	}

	/**
	 * Render every child of a parent.
	 *
	 * @param Field                $owner  Parent field.
	 * @param array<string, mixed> $values Current values keyed by child key.
	 * @param string               $prefix Input-name prefix.
	 * @param string               $scope  Extra id discriminator.
	 *
	 * @return string
	 */
	protected function render_children( Field $owner, array $values, string $prefix, string $scope = '' ): string {
		$renderer = new Renderer();
		$markup   = '';

		foreach ( $owner->sub_fields() as $key => $config ) {
			$child = $this->child( $owner, (string) $key, (array) $config, $values[ $key ] ?? null, $prefix, $scope );

			if ( null !== $child ) {
				$markup .= $renderer->render( $child );
			}
		}

		return $markup;
	}

	/**
	 * Sanitize every child of a parent.
	 *
	 * @param Field $owner Parent field.
	 * @param mixed $value  Raw submitted value for the whole group.
	 *
	 * @return array<string, mixed>
	 */
	protected function sanitize_children( Field $owner, mixed $value ): array {
		$registry = $this->registry ?? new Registry();
		$value    = is_array( $value ) ? $value : [];
		$clean    = [];

		foreach ( $owner->sub_fields() as $key => $config ) {
			$type = (string) ( $config['type'] ?? 'text' );

			if ( ! $registry->has( $type ) ) {
				continue;
			}

			$resolved = $registry->get( $type );

			if ( ! $resolved->stores_value() ) {
				continue;
			}

			$child = new Field(
				(string) $key,
				$resolved,
				array_merge( $resolved->defaults(), (array) $config ),
				null
			);

			$clean[ $key ] = $resolved->sanitize( $value[ $key ] ?? null, $child );
		}

		return $clean;
	}

	/**
	 * An object built from the type's own children.
	 *
	 * Derived rather than declared, so a group's schema cannot drift from the
	 * fields it actually renders.
	 *
	 * @param Field $field The field.
	 *
	 * @return array<string, mixed>
	 */
	public function schema( Field $field ): array {
		$registry   = $this->registry ?? new Registry();
		$properties = [];

		foreach ( $field->sub_fields() as $key => $config ) {
			$type = (string) ( $config['type'] ?? 'text' );

			if ( ! $registry->has( $type ) ) {
				continue;
			}

			$child = $registry->get( $type );

			if ( ! $child->stores_value() ) {
				continue;
			}

			$properties[ (string) $key ] = $child->schema(
				new Field( (string) $key, $child, array_merge( $child->defaults(), (array) $config ), null )
			);
		}

		return [
			'type'       => 'object',
			'properties' => $properties,
		];
	}
}
