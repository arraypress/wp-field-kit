<?php
/**
 * Post Meta Context
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Context;

/**
 * Fields stored as post metadata.
 */
final class PostMetaContext extends AbstractMetaContext {

	/**
	 * The metadata type.
	 *
	 * @return string
	 */
	public function meta_type(): string {
		return 'post';
	}
}
