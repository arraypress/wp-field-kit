<?php
/**
 * Comment Meta Context
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Context;

/**
 * Fields stored as comment metadata.
 */
final class CommentMetaContext extends AbstractMetaContext {

	/**
	 * The metadata type.
	 *
	 * @return string
	 */
	public function meta_type(): string {
		return 'comment';
	}
}
