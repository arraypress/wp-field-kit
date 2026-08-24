<?php
/**
 * Term Meta Context
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Context;

/**
 * Fields stored as term metadata.
 */
final class TermMetaContext extends AbstractMetaContext {

	/**
	 * The metadata type.
	 *
	 * @return string
	 */
	public function meta_type(): string {
		return 'term';
	}
}
