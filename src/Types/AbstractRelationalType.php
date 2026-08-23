<?php
/**
 * Base Relational Type
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Attributes;
use ArrayPress\FieldKit\Field;
use ArrayPress\FieldKit\Utils\Runtime;

/**
 * Anything that searches a database source for its options.
 *
 * There were three implementations of this idea across the predecessor
 * libraries — setting-fields had a REST route, post-fields had its own, and
 * flyouts had a `search_callback` on top of admin-ajax. All three resolve to
 * one endpoint here, with one contract.
 *
 * The control stays a real `<select>` carrying the current selection as
 * options. That matters twice over: the field still works when the
 * enhancement script does not load, and the enhancement has a genuine
 * element to mirror instead of inventing state. The searchable overlay is
 * expected to implement the ARIA combobox pattern against it; the data
 * attributes below are what it binds to.
 */
abstract class AbstractRelationalType extends SelectType {

	/**
	 * The source the search endpoint queries.
	 *
	 * @return string
	 */
	abstract protected function source(): string;

	/**
	 * Resolve the labels for the currently selected ids.
	 *
	 * Only the selection is rendered as options — the point of searching is
	 * that the full list is too large to emit.
	 *
	 * @param int[] $ids Selected ids.
	 * @param Field $field The field.
	 *
	 * @return array<string, string>
	 */
	abstract protected function resolve_labels( array $ids, Field $field ): array;

	/**
	 * Query arguments forwarded to the endpoint.
	 *
	 * @param Field $field The field.
	 *
	 * @return array<string, scalar>
	 */
	protected function search_args( Field $field ): array {
		return [];
	}

	/**
	 * Options are only ever the current selection.
	 *
	 * @param Field $field The field.
	 *
	 * @return string
	 */
	protected function render_options( Field $field ): string {
		$ids    = array_values( array_filter( array_map( 'absint', $this->selected_values( $field ) ) ) );
		$markup = '';

		if ( ! $this->is_multiple( $field ) && ! $field->is_required() ) {
			$markup .= sprintf(
				'<option value="">%s</option>',
				esc_html( $field->placeholder() ?: __( '— Select —', 'arraypress' ) )
			);
		}

		foreach ( $this->resolve_labels( $ids, $field ) as $id => $label ) {
			$markup .= sprintf(
				'<option value="%s" selected>%s</option>',
				esc_attr( (string) $id ),
				esc_html( $label )
			);
		}

		return $markup;
	}

	/**
	 * Render the select with the search wiring attached.
	 *
	 * @param Field      $field      The field.
	 * @param Attributes $attributes Prepared attributes.
	 *
	 * @return string
	 */
	public function render( Field $field, Attributes $attributes ): string {
		$attributes->add_class( 'field-kit__relational' );
		$attributes->set( 'data-search-endpoint', rest_url( Runtime::rest_namespace() . '/search' ) );
		$attributes->set( 'data-search-source', $this->source() );
		$attributes->set( 'data-search-nonce', wp_create_nonce( 'wp_rest' ) );
		$attributes->set_if( $field->has( 'min_chars' ), 'data-min-chars', $field->get( 'min_chars', 2 ) );

		$args = $this->search_args( $field );

		$attributes->set_if( [] !== $args, 'data-search-args', $args );

		// A field with a callback is resolved server-side by key, so the
		// callable itself never reaches the page.
		$attributes->set_if( $field->has( 'search_callback' ), 'data-search-field', $field->key() );

		return parent::render( $field, $attributes );
	}

	/**
	 * Always searchable.
	 *
	 * @param Field $field The field.
	 *
	 * @return bool
	 */
	protected function uses_enhanced_ui( Field $field ): bool {
		return true;
	}

	/**
	 * The option list is not a closed allow-list here.
	 *
	 * @param Field $field The field.
	 *
	 * @return string[]
	 */
	protected function allowed_values( Field $field ): array {
		return [];
	}

	/**
	 * Coerce a submitted value to ids.
	 *
	 * @param mixed $value Raw submitted value.
	 * @param Field $field The field.
	 *
	 * The return type stays wide: most sources key on an integer id, but a
	 * custom one can key on a slug or an external reference, and a narrowed
	 * type here would stop that subclass declaring what it actually stores.
	 *
	 * @return mixed
	 */
	public function sanitize( mixed $value, Field $field ): mixed {
		if ( $this->is_multiple( $field ) ) {
			return array_values( array_filter( array_map( 'absint', (array) $value ) ) );
		}

		return absint( is_array( $value ) ? reset( $value ) : $value );
	}
}
