<?php
/**
 * Custom AJAX Relational Type
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Field;

/**
 * A searchable field backed by the consumer's own callback.
 *
 * The callback is named in the field config and invoked server-side by the
 * search endpoint; it is never referenced from the page. Its results are also
 * what resolves the current selection's labels, so a stored value shows its
 * name rather than a bare id.
 */
final class AjaxType extends AbstractRelationalType {

	/**
	 * The search source.
	 *
	 * @return string
	 */
	protected function source(): string {
		return 'callback';
	}

	/**
	 * The source this field actually searches.
	 *
	 * Set by the field set when it registered the callback, so the name in
	 * the page corresponds to something real. Without it the field asked for
	 * a source called "callback" that nothing had ever registered, and every
	 * search came back empty.
	 *
	 * @param Field $field The field.
	 *
	 * @return string
	 */
	public function source_for( Field $field ): string {
		return (string) $field->get( 'search_source', $this->source() );
	}

	/**
	 * Resolve labels through the configured callback.
	 *
	 * A `label_callback` is preferred when supplied, since resolving a known
	 * set of ids is usually a different query from searching for them.
	 *
	 * @param int[] $ids   Selected ids.
	 * @param Field $field The field.
	 *
	 * @return array<string, string>
	 */
	protected function resolve_labels( array $ids, Field $field ): array {
		if ( [] === $ids ) {
			return [];
		}

		$callback = $field->get( 'label_callback', $field->get( 'search_callback' ) );

		if ( ! is_callable( $callback ) ) {
			return array_combine(
				array_map( 'strval', $ids ),
				array_map( 'strval', $ids )
			);
		}

		$labels  = [];
		$results = $callback( '', $ids, $field );

		foreach ( (array) $results as $result ) {
			$id = (string) ( $result['id'] ?? '' );

			if ( '' !== $id && in_array( (int) $id, $ids, true ) ) {
				$labels[ $id ] = (string) ( $result['text'] ?? $id );
			}
		}

		return $labels;
	}

	/**
	 * The stored value is not necessarily an id.
	 *
	 * A custom source can key on a slug or an external reference, so the
	 * value is kept as text rather than forced through absint().
	 *
	 * @param mixed $value Raw submitted value.
	 * @param Field $field The field.
	 *
	 * @return string|string[]
	 */
	public function sanitize( mixed $value, Field $field ): string|array {
		if ( $this->is_multiple( $field ) ) {
			return array_values( array_filter( array_map( 'sanitize_text_field', (array) $value ) ) );
		}

		return sanitize_text_field( (string) ( is_array( $value ) ? reset( $value ) : $value ) );
	}
}
