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
	 * The source this field actually searches.
	 *
	 * Usually the type's own name. A type whose source is decided per field
	 * rather than per type overrides this — an AJAX field's callback is
	 * registered under a name derived from where the field lives, so the name
	 * in the page has to be that one and not the type's.
	 *
	 * Declared here rather than asked for with method_exists() at the call
	 * site, because a class asking whether it has its own method is a class
	 * that has not decided what its interface is — and it hides the answer
	 * from every tool that would otherwise check the call.
	 *
	 * @param Field $field The field.
	 *
	 * @return string
	 */
	protected function source_for( Field $field ): string {
		return $this->source();
	}

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

		$labels = $this->resolve_labels( $ids, $field );

		// A created value never had an id to resolve, so it labels itself.
		// Without this a tag someone added disappears from the control on the
		// next load while still being stored.
		if ( (bool) $field->get( 'creatable', false ) ) {
			foreach ( array_map( 'strval', (array) $this->selected_values( $field ) ) as $raw ) {
				if ( '' !== $raw && ! isset( $labels[ $raw ] ) ) {
					$labels[ $raw ] = $raw;
				}
			}
		}

		foreach ( $labels as $id => $label ) {
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
		$attributes->set( 'data-search-source', $this->source_for( $field ) );
		$attributes->set( 'data-search-nonce', wp_create_nonce( 'wp_rest' ) );
		$attributes->set_if( $field->has( 'min_chars' ), 'data-min-chars', $field->get( 'min_chars', 2 ) );

		$args = $this->search_args( $field );

		$attributes->set_if( [] !== $args, 'data-search-args', $args );

		// A field with a callback is resolved server-side by key, so the
		// callable itself never reaches the page.
		$attributes->set_if( $field->has( 'search_callback' ), 'data-search-field', $field->key() );

		$attributes->set_if( (bool) $field->get( 'creatable', false ), 'data-creatable', 'true' );

		/*
		 * Order is only meaningful when more than one thing can be chosen, and
		 * it is only worth carrying when the caller says so -- a set of related
		 * products has an order the editor cares about, a set of tags does not.
		 */
		$attributes->set_if(
			$this->is_multiple( $field ) && (bool) $field->get( 'sortable', false ),
			'data-sortable',
			'true'
		);

		$attributes->set_if( $field->has( 'min' ), 'data-min', (int) $field->get( 'min', 0 ) );
		$attributes->set_if( $field->has( 'max' ), 'data-max', (int) $field->get( 'max', 0 ) );

		return parent::render( $field, $attributes );
	}

	/**
	 * A search control says so.
	 *
	 * "Choose one or more" is right for a list already on the page and wrong
	 * for one that is fetched as you type: nothing is on offer until a query
	 * is typed, so the instruction has to be to type.
	 *
	 * @param Field $field The field.
	 *
	 * @return string
	 */
	protected function default_placeholder( Field $field ): string {
		if ( (bool) $field->get( 'creatable', false ) ) {
			return __( 'Search, or type to add', 'arraypress' );
		}

		return $this->is_multiple( $field )
			? __( 'Search to add one or more', 'arraypress' )
			: __( 'Type to search', 'arraypress' );
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
		// A creatable relational field stores whatever was typed alongside
		// the ids it found — a new tag has no id yet, and absint() would
		// turn it into 0 and throw it away.
		if ( (bool) $field->get( 'creatable', false ) ) {
			$values = array_map( 'sanitize_text_field', array_map( 'strval', (array) $value ) );
			$values = array_values( array_filter( $values, static fn( $one ) => '' !== $one ) );

			return $this->is_multiple( $field )
				? $this->apply_max( $values, $field )
				: ( $values[0] ?? '' );
		}

		if ( $this->is_multiple( $field ) ) {
			return $this->apply_max(
				array_values( array_filter( array_map( 'absint', (array) $value ) ) ),
				$field
			);
		}

		$id = absint( is_array( $value ) ? reset( $value ) : $value );

		// Nothing selected comes back as '' rather than 0, the same as an
		// emptied media field. Zero is a value as far as the field set is
		// concerned — an unticked checkbox deliberately stores it — so
		// returning it here writes a post id of 0 for every relational field
		// nobody touched, and get_the_title( 0 ) is not a post that exists.
		return 0 === $id ? '' : $id;
	}

	/**
	 * An object id, or a list of them.
	 *
	 * A creatable field stores text as well as ids — a value someone typed
	 * has no id yet — so its schema cannot promise integers.
	 *
	 * @param Field $field The field.
	 *
	 * @return array<string, mixed>
	 */
	public function schema( Field $field ): array {
		$one = (bool) $field->get( 'creatable', false )
			? [ 'type' => 'string' ]
			: [ 'type' => 'integer' ];

		return $this->is_multiple( $field )
			? [
				'type'  => 'array',
				'items' => $one,
			]
			: $one;
	}

	/**
	 * The configuration keys this type reads.
	 *
	 * @return string[]
	 */
	public function config_keys(): array {
		// 'creatable' is already declared by SelectType; repeating it here put
		// it in the list twice.
		return array_merge(
			parent::config_keys(),
			[ 'min_chars', 'search_callback', 'sortable', 'min', 'max' ]
		);
	}

	/**
	 * Cut a selection down to the configured maximum.
	 *
	 * The control refuses to take more than `max`, but the control is JavaScript
	 * and the value arrives over HTTP. A limit enforced only in the browser is
	 * not a limit -- anything posting directly ignores it -- so the extra values
	 * are dropped here as well.
	 *
	 * Extras are cut from the end, so the editor keeps what they chose first.
	 *
	 * @param array $values The sanitised selection.
	 * @param Field $field  The field.
	 *
	 * @return array
	 * @since 1.1.0
	 */
	protected function apply_max( array $values, Field $field ): array {
		$max = (int) $field->get( 'max', 0 );

		return $max > 0 ? array_slice( $values, 0, $max ) : $values;
	}
}
