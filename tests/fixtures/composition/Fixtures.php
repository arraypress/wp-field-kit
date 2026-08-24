<?php
/**
 * Fixtures for the self-reference checker.
 *
 * Deliberately shaped wrong in places — that is what they are for. Kept in
 * one file so the checker's own directory scan sees a realistic mix, and out
 * of `src` so nothing ships them.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Tests\Fixtures\Composition;

/**
 * A trait whose calls all resolve in the class that composes it.
 */
trait Sound {

	/**
	 * Calls a sibling trait's method, which is fine at runtime.
	 *
	 * @return string
	 */
	public function greet(): string {
		return self::punctuate( 'hello' ) . $this->shout();
	}
}

/**
 * The sibling.
 */
trait Punctuation {

	/**
	 * @param string $text Text.
	 *
	 * @return string
	 */
	public static function punctuate( string $text ): string {
		return $text . '!';
	}
}

/**
 * A trait that calls something no composing class has.
 */
trait Broken {

	/**
	 * @return string
	 */
	public function fail(): string {
		return self::punctuate( 'x' ) . $this->never_defined_anywhere();
	}
}

/**
 * A trait nothing uses.
 */
trait Orphan {

	/**
	 * @return string
	 */
	public function unused(): string {
		return 'nobody composes me';
	}
}

/**
 * The composing class. Has everything Sound needs and nothing Broken needs.
 */
class Host {

	use Sound;
	use Punctuation;
	use Broken;

	/**
	 * @return string
	 */
	public function shout(): string {
		return 'HI';
	}
}
