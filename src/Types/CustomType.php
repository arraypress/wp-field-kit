<?php
/**
 * Custom Field Type
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Attributes;
use ArrayPress\FieldKit\Field;

/**
 * An escape hatch: the consumer renders and sanitizes the control itself.
 *
 * The renderer still supplies the wrapper, label and accessibility
 * associations, and hands the prepared attributes to the callback — so even a
 * hand-written control inherits the labelling rather than starting from
 * nothing. A callback that ignores them is on its own, which is why they are
 * passed rather than merely available.
 */
final class CustomType extends AbstractType {

	/**
	 * Render through the configured callback.
	 *
	 * @param Field      $field      The field.
	 * @param Attributes $attributes Prepared attributes.
	 *
	 * @return string
	 */
	public function render( Field $field, Attributes $attributes ): string {
		$callback = $field->get( 'render_callback' );

		if ( ! is_callable( $callback ) ) {
			return '';
		}

		// A callback written against a different library's contract — an
		// array-and-strings signature rather than this one — would otherwise
		// throw a TypeError and take the whole screen down with it. One
		// consumer's mistyped callback is not a reason to white-screen an
		// admin page, so the signature is checked before it is called.
		if ( ! $this->accepts_contract( $callback ) ) {
			_doing_it_wrong(
				__METHOD__,
				sprintf(
					/* translators: %s: field key */
					esc_html__(
						'The render_callback for "%s" does not accept the field kit\'s arguments. It is called with ( Field $field, Attributes $attributes ).',
						'arraypress'
					),
					esc_html( $field->key() )
				),
				'1.0.0'
			);

			return '';
		}

		ob_start();

		$returned = $callback( $field, $attributes );
		$echoed   = (string) ob_get_clean();

		// A callback may echo or return; supporting both means neither
		// convention silently renders nothing.
		return '' !== $echoed ? $echoed : (string) $returned;
	}

	/**
	 * Whether a callback can accept this type's arguments.
	 *
	 * Only declared types are checked: a callback that types its first
	 * parameter as something other than Field cannot be ours. An untyped or
	 * variadic callback is allowed through, since there is nothing to
	 * contradict.
	 *
	 * @param callable $callback The callback.
	 *
	 * @return bool
	 */
	private function accepts_contract( callable $callback ): bool {
		try {
			$reflection = is_array( $callback )
				? new \ReflectionMethod( $callback[0], $callback[1] )
				: new \ReflectionFunction( \Closure::fromCallable( $callback ) );
		} catch ( \ReflectionException $e ) {
			return true;
		}

		$parameters = $reflection->getParameters();

		if ( [] === $parameters ) {
			return true;
		}

		$type = $parameters[0]->getType();

		// Nothing declared, or a union or intersection: there is no single
		// name to contradict, so it is allowed through.
		if ( ! $type instanceof \ReflectionNamedType ) {
			return true;
		}

		// A builtin first parameter — array, string, int — is another
		// library's signature. This type always passes an object.
		if ( $type->isBuiltin() ) {
			return false;
		}

		return is_a( $type->getName(), Field::class, true );
	}

	/**
	 * Coerce a submitted value through the configured callback.
	 *
	 * @param mixed $value Raw submitted value.
	 * @param Field $field The field.
	 *
	 * @return mixed
	 */
	public function sanitize( mixed $value, Field $field ): mixed {
		$callback = $field->get( 'sanitize_callback' );

		if ( is_callable( $callback ) ) {
			return $callback( $value, $field );
		}

		// No callback means no idea what shape this is, so the conservative
		// default applies rather than storing it untouched.
		return is_array( $value )
			? array_map( 'sanitize_text_field', $value )
			: sanitize_text_field( (string) $value );
	}
}
