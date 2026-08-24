<?php
/**
 * Self-reference checker.
 *
 * Shared test infrastructure. Not part of the library's runtime — it lives
 * beside the stubs the consuming libraries already require, because every one
 * of them wants this check and none of them should carry its own copy.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Tests;

use ReflectionClass;

/**
 * Does every `self::` in a library point at something that exists?
 *
 * PHP will not tell you. A property nobody declared, a constant deleted with
 * the feature that used it, a method removed while its last caller stayed —
 * all of it parses, all of it lints, and every one is a fatal the moment that
 * line runs. `php -l` says nothing, and neither does a suite that never
 * reaches the branch.
 *
 * Not hypothetical: removing a vendored search library from an asset manager
 * took its handle constant with it and, in the same edit, the properties and
 * the registration method that had nothing to do with it. The file linted
 * clean and white-screened every admin page the library loaded on.
 *
 * Tokens rather than a regular expression, so a mention in a comment or a
 * string is not a reference.
 */
final class SelfReferences {

	/**
	 * Every unresolvable self-reference under a directory.
	 *
	 * @param string $directory Absolute path to a library's `src`.
	 *
	 * @return string[] One line per problem, empty when there are none.
	 */
	public static function broken( string $directory ): array {
		$broken = [];

		foreach ( self::sources( $directory ) as $path ) {
			foreach ( self::references( $path ) as [ $class, $kind, $name, $line ] ) {
				// A class the autoloader cannot produce is not this check's
				// business — it would report every reference in the file for
				// one unrelated reason.
				if ( ! class_exists( $class ) && ! interface_exists( $class ) && ! trait_exists( $class ) ) {
					continue;
				}

				$mirror = new ReflectionClass( $class );

				$exists = match ( $kind ) {
					'property' => $mirror->hasProperty( $name ),
					'method'   => $mirror->hasMethod( $name ),
					default    => $mirror->hasConstant( $name ),
				};

				if ( ! $exists ) {
					$broken[] = sprintf(
						'%s:%d  self::%s%s is not a declared %s',
						basename( $path ),
						$line,
						'property' === $kind ? '$' : '',
						$name,
						$kind
					);
				}
			}
		}

		return $broken;
	}

	/**
	 * The PHP files under a directory.
	 *
	 * @param string $directory Absolute path.
	 *
	 * @return string[]
	 */
	private static function sources( string $directory ): array {
		if ( ! is_dir( $directory ) ) {
			return [];
		}

		$files = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $directory ) );
		$paths = [];

		foreach ( $files as $file ) {
			if ( $file->isFile() && 'php' === $file->getExtension() ) {
				$paths[] = $file->getPathname();
			}
		}

		sort( $paths );

		return $paths;
	}

	/**
	 * Every self-reference in one file.
	 *
	 * @param string $path Absolute path.
	 *
	 * @return array<int, array{0: string, 1: string, 2: string, 3: int}>
	 */
	private static function references( string $path ): array {
		// The noise dropped, so "the next token" means the next one that
		// matters rather than the whitespace before it.
		$tokens = array_values(
			array_filter(
				token_get_all( (string) file_get_contents( $path ) ),
				static fn( $token ) => ! is_array( $token )
					|| ! in_array( $token[0], [ T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ], true )
			)
		);

		$namespace = '';
		$class     = '';
		$found     = [];

		foreach ( $tokens as $index => $token ) {
			if ( ! is_array( $token ) ) {
				continue;
			}

			if ( T_NAMESPACE === $token[0] && is_array( $tokens[ $index + 1 ] ?? null ) ) {
				$namespace = (string) $tokens[ $index + 1 ][1];
				continue;
			}

			// A named declaration. Not an anonymous class, whose next token is
			// `(` or `{`, and not `::class`, whose previous one is `::`.
			if ( in_array( $token[0], [ T_CLASS, T_INTERFACE, T_TRAIT ], true )
				&& is_array( $tokens[ $index + 1 ] ?? null )
				&& T_STRING === $tokens[ $index + 1 ][0] ) {

				$class = $namespace . '\\' . $tokens[ $index + 1 ][1];
				continue;
			}

			if ( T_STRING !== $token[0]
				|| ! in_array( strtolower( (string) $token[1] ), [ 'self', 'static' ], true ) ) {
				continue;
			}

			if ( ! is_array( $tokens[ $index + 1 ] ?? null ) || T_DOUBLE_COLON !== $tokens[ $index + 1 ][0] ) {
				continue;
			}

			$target = $tokens[ $index + 2 ] ?? null;

			if ( ! is_array( $target ) || '' === $class ) {
				continue;
			}

			if ( T_VARIABLE === $target[0] ) {
				$found[] = [ $class, 'property', ltrim( (string) $target[1], '$' ), (int) $target[2] ];
				continue;
			}

			if ( T_STRING !== $target[0] ) {
				continue;
			}

			// Always available.
			if ( 'class' === strtolower( (string) $target[1] ) ) {
				continue;
			}

			$found[] = [
				$class,
				'(' === ( $tokens[ $index + 3 ] ?? '' ) ? 'method' : 'constant',
				(string) $target[1],
				(int) $target[2],
			];
		}

		return $found;
	}
}
