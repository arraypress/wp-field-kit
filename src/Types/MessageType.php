<?php
/**
 * Message Field Type
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Attributes;
use ArrayPress\FieldKit\Field;

/**
 * An inline notice.
 *
 * Uses core's notice markup so it matches every other admin message, and
 * carries a role only when the message is a warning or an error — a
 * permanently present informational note announced as an alert is noise.
 */
final class MessageType extends AbstractLayoutType {

	/**
	 * Notice styles core provides.
	 *
	 * @var string[]
	 */
	private const LEVELS = [ 'info', 'success', 'warning', 'error' ];

	/**
	 * Config defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return [ 'level' => 'info' ];
	}

	/**
	 * Render the notice.
	 *
	 * @param Field      $field      The field.
	 * @param Attributes $attributes Prepared attributes.
	 *
	 * @return string
	 */
	public function render( Field $field, Attributes $attributes ): string {
		$level = (string) $field->get( 'level', 'info' );
		$level = in_array( $level, self::LEVELS, true ) ? $level : 'info';

		$notice = new Attributes();
		$notice->add_class( 'notice', 'notice-' . $level, 'inline', 'field-kit__message' );

		if ( in_array( $level, [ 'warning', 'error' ], true ) ) {
			$notice->set( 'role', 'alert' );
		}

		return sprintf(
			'<div%s><p>%s</p></div>',
			$notice->render(),
			// Only the message. Falling back to the description printed the
			// same text twice, since the renderer appends that too.
			wp_kses_post( (string) $field->get( 'message', '' ) )
		);
	}
}
