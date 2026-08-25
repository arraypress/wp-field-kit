<?php
/**
 * Are the documented field configurations real?
 *
 * A configuration key nothing reads is not an error in PHP. The array entry
 * sits there, the control renders with its defaults, and the only symptom is
 * that a documented option quietly does nothing — which is how a set of
 * examples came to use `button_label` on a repeater twenty-three times, when
 * the add button has always been `add_label`.
 *
 * This reads the fenced PHP in a library's markdown, pulls out every field
 * configuration it can find, and checks each one against the registry and
 * against what the type says it reads.
 *
 * Usage, from a library that depends on the kit:
 *
 *   php vendor/arraypress/wp-field-kit/bin/verify-docs.php README.md EXAMPLES.md
 *
 * A library with a vocabulary of its own declares it:
 *
 *   ... --types=header,info_grid --keys=tab docs/*.md
 *
 * And which calls in its documentation register fields, so a README
 * demonstrating another library is not read as this one's configuration:
 *
 *   ... --calls=register_user_fields README.md
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

use ArrayPress\FieldKit\Field;
use ArrayPress\FieldKit\Registry;

// The library being checked, or the kit itself.
foreach ( [ __DIR__ . '/../../../autoload.php', __DIR__ . '/../vendor/autoload.php' ] as $autoload ) {
	if ( file_exists( $autoload ) ) {
		require $autoload;
		break;
	}
}

if ( ! class_exists( Registry::class ) ) {
	fwrite( STDERR, "The kit is not autoloadable from here.\n" );
	exit( 1 );
}

/*
 * A consuming library has a vocabulary of its own. Flyouts documents `header`
 * and `info_grid`, which are components it draws rather than field types the
 * kit registers, and a `tab` key naming which tab a field belongs to, which
 * the flyout reads and the kit never sees. Reporting those as errors would
 * mean the tool cannot be used in the library that most needs it — and a
 * check that always reports something is a check nobody runs.
 *
 * So both are declarable:
 *
 *   --types=header,info_grid  types this library draws itself
 *   --keys=tab,wrapper_class  keys this library reads itself
 */
$extra_types = [];
$extra_keys  = [];
$only_calls  = [];
$files       = [];

foreach ( array_slice( $argv, 1 ) as $argument ) {
	if ( str_starts_with( $argument, '--types=' ) ) {
		$extra_types = array_filter( array_map( 'trim', explode( ',', substr( $argument, 8 ) ) ) );
		continue;
	}

	if ( str_starts_with( $argument, '--keys=' ) ) {
		$extra_keys = array_filter( array_map( 'trim', explode( ',', substr( $argument, 7 ) ) ) );
		continue;
	}

	if ( str_starts_with( $argument, '--calls=' ) ) {
		$only_calls = array_filter( array_map( 'trim', explode( ',', substr( $argument, 8 ) ) ) );
		continue;
	}

	$files[] = $argument;
}

if ( [] === $files ) {
	$files = array_values( array_filter( [ 'README.md', 'EXAMPLES.md' ], 'file_exists' ) );
}

/*
 * A library that adds keys of its own already says so, in code:
 *
 *   Field::allow_config_keys( [ 'own_capability', 'show_on_add' ] );
 *
 * It runs when the library boots, which is not something this tool can make
 * happen — it reads markdown, not a WordPress request. So the declarations are
 * read out of the source instead, and a library that has already declared a
 * key does not have to declare it again on a command line. Two places to keep
 * in step is how a key ends up allowed in one and reported in the other.
 */
$extra_keys = array_merge( $extra_keys, declared_config_keys( getcwd() . '/src' ) );

/**
 * The extra configuration keys a library declares in its own source.
 *
 * @param string $directory The library's `src`.
 *
 * @return string[]
 */
function declared_config_keys( string $directory ): array {
	if ( ! is_dir( $directory ) ) {
		return [];
	}

	$keys  = [];
	$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $directory ) );

	foreach ( $files as $file ) {
		if ( ! $file->isFile() || 'php' !== $file->getExtension() ) {
			continue;
		}

		if ( ! preg_match_all(
			'/allow_config_keys\(\s*\[(.*?)\]/s',
			(string) file_get_contents( $file->getPathname() ),
			$calls
		) ) {
			continue;
		}

		foreach ( $calls[1] as $literal ) {
			// Quoted strings only. A variable or a constant is not something
			// that can be read out of the source, and a key this misses is
			// reported rather than silently allowed.
			preg_match_all( '/[\'"]([a-z0-9_]+)[\'"]/i', $literal, $quoted );

			$keys = array_merge( $keys, $quoted[1] );
		}
	}

	return array_values( array_unique( $keys ) );
}

/**
 * Every fenced PHP block in a markdown file, with the line it starts on.
 *
 * @param string $markdown File contents.
 *
 * @return array<int, array{0: int, 1: string}>
 */
function fenced_php( string $markdown ): array {
	$lines  = explode( "\n", $markdown );
	$blocks = [];
	$open   = null;
	$buffer = [];

	foreach ( $lines as $number => $line ) {
		if ( null === $open && preg_match( '/^```\s*php\s*$/', $line ) ) {
			$open   = $number + 2;
			$buffer = [];
			continue;
		}

		if ( null !== $open && preg_match( '/^```\s*$/', $line ) ) {
			$blocks[] = [ $open, implode( "\n", $buffer ) ];
			$open     = null;
			continue;
		}

		if ( null !== $open ) {
			$buffer[] = $line;
		}
	}

	return $blocks;
}

/**
 * The value of a PHP array literal, without running it.
 *
 * Without running it, because this tool reads documentation, and
 * documentation is untrusted the moment anyone but its author edits it. The
 * obvious implementation *executes* whatever a README fence contains.
 *
 * There is a second reason. This file ships: Composer installs bin/ alongside
 * src/, so it lands in the vendor tree of every plugin that depends on the
 * kit, and the runtime code interpreter is the first thing a WordPress
 * security scanner looks for. A dev tool is not worth a scanner warning on
 * somebody's live site.
 *
 * So the literal is tokenized and walked. Only constants are recognised --
 * strings, numbers, true/false/null, arrays of those, and the translation
 * calls in TRANSLATION_CALLS -- and anything else throws, which puts a block
 * containing a function call in the same bucket as one that will not parse:
 * skipped, never run.
 *
 * @param string $literal A PHP array literal.
 *
 * @return mixed The value.
 *
 * @throws UnexpectedValueException When the literal is not constant.
 */
function literal_value( string $literal ) {
	// The lenient mode on purpose: TOKEN_PARSE would raise on a literal that
	// does not compile, and documentation is full of ones that do not. A stream
	// of tokens that means nothing walks into the same refusal as any other
	// non-constant expression.
	$tokens = token_get_all( '<?php ' . $literal . ';' );

	// Whitespace and comments carry nothing.
	$tokens = array_values(
		array_filter(
			$tokens,
			static fn( $token ) => ! is_array( $token )
				|| ! in_array( $token[0], [ T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_OPEN_TAG ], true )
		)
	);

	$position = 0;
	$value    = read_value( $tokens, $position );

	// Everything but the trailing `;` should be consumed.
	if ( $position < count( $tokens ) && ';' !== $tokens[ $position ] ) {
		throw new UnexpectedValueException( 'Trailing tokens.' );
	}

	return $value;
}

/**
 * Read one constant value, advancing the position past it.
 *
 * @param array<int, array{0: int, 1: string}|string> $tokens   The token stream.
 * @param int                                         $position Cursor, by reference.
 *
 * @return mixed
 *
 * @throws UnexpectedValueException When the next token is not a constant.
 */
function read_value( array $tokens, int &$position ) {
	$token = $tokens[ $position ] ?? null;

	if ( null === $token ) {
		throw new UnexpectedValueException( 'Ran out of tokens.' );
	}

	// A negative number is a minus and a number, not one token.
	if ( '-' === $token || '+' === $token ) {
		++$position;
		$number = read_value( $tokens, $position );

		if ( ! is_int( $number ) && ! is_float( $number ) ) {
			throw new UnexpectedValueException( 'Sign on a non-number.' );
		}

		return '-' === $token ? -$number : $number;
	}

	if ( '[' === $token ) {
		return read_array( $tokens, $position );
	}

	if ( ! is_array( $token ) ) {
		throw new UnexpectedValueException( 'Not a constant: ' . $token );
	}

	++$position;

	switch ( $token[0] ) {
		case T_CONSTANT_ENCAPSED_STRING:
			return unquote( $token[1] );

		case T_LNUMBER:
			return (int) $token[1];

		case T_DNUMBER:
			return (float) $token[1];

		case T_ARRAY:
			// The long form: array( ... ).
			if ( '(' !== ( $tokens[ $position ] ?? null ) ) {
				throw new UnexpectedValueException( 'array without a bracket.' );
			}

			return read_array( $tokens, $position, ')' );

		case T_NS_SEPARATOR:
			// A leading backslash on a global function.
			return read_value( $tokens, $position );

		case T_STRING:
			$name = strtolower( $token[1] );

			if ( in_array( $name, TRANSLATION_CALLS, true ) ) {
				return read_translation( $tokens, $position );
			}

			return match ( $name ) {
				'true'  => true,
				'false' => false,
				'null'  => null,
				default => throw new UnexpectedValueException( 'Not a constant: ' . $token[1] ),
			};
	}

	throw new UnexpectedValueException( 'Not a constant: ' . token_name( $token[0] ) );
}

/**
 * The translation functions a documented configuration may call.
 *
 * These are the only calls recognised, and what is taken from them is the
 * literal being translated -- the tool never loads WordPress, and a label's
 * text domain has no bearing on whether the key it sits under is real.
 *
 * Anything outside this list throws, so a configuration built by a function
 * call is skipped rather than run.
 */
const TRANSLATION_CALLS = [ '__', '_x', '_n', '_nx', 'esc_html__', 'esc_attr__', 'esc_html_x', 'esc_attr_x' ];

/**
 * Read a translation call, returning the string it wraps.
 *
 * @param array<int, array{0: int, 1: string}|string> $tokens   The token stream.
 * @param int                                         $position Cursor, by reference, on the opening parenthesis.
 *
 * @return string
 *
 * @throws UnexpectedValueException When the arguments are not constant.
 */
function read_translation( array $tokens, int &$position ): string {
	if ( '(' !== ( $tokens[ $position ] ?? null ) ) {
		throw new UnexpectedValueException( 'A translation function without arguments.' );
	}

	++$position;

	$arguments = [];

	while ( ')' !== ( $tokens[ $position ] ?? null ) ) {
		$arguments[] = read_value( $tokens, $position );

		if ( ',' === ( $tokens[ $position ] ?? null ) ) {
			++$position;
		}
	}

	// Past the closing parenthesis.
	++$position;

	if ( [] === $arguments || ! is_string( $arguments[0] ) ) {
		throw new UnexpectedValueException( 'A translation function without a string.' );
	}

	return $arguments[0];
}

/**
 * Read an array literal, advancing past its closing bracket.
 *
 * @param array<int, array{0: int, 1: string}|string> $tokens   The token stream.
 * @param int                                         $position Cursor, by reference, on the opening bracket.
 * @param string                                      $close    The closing character.
 *
 * @return array<array-key, mixed>
 *
 * @throws UnexpectedValueException When the array is not constant.
 */
function read_array( array $tokens, int &$position, string $close = ']' ): array {
	// Past the opening bracket.
	++$position;

	$array = [];

	while ( true ) {
		$token = $tokens[ $position ] ?? null;

		if ( null === $token ) {
			throw new UnexpectedValueException( 'Unclosed array.' );
		}

		if ( $close === $token ) {
			++$position;

			return $array;
		}

		$before = $position;
		$value  = read_entry( $tokens, $position );

		// `key => value` rather than a bare value.
		if ( is_array( $tokens[ $position ] ?? null ) && T_DOUBLE_ARROW === $tokens[ $position ][0] ) {
			++$position;

			$entry = read_entry( $tokens, $position );

			if ( is_int( $value ) || is_string( $value ) ) {
				$array[ $value ] = $entry;
			}

			// A key that is itself an expression -- a constant, a variable --
			// has nothing to file the entry under, so the entry is dropped.
			// It has been read either way, which is what keeps the cursor in
			// the right place.
		} else {
			$array[] = $value;
		}

		// A comma, or the end.
		if ( ',' === ( $tokens[ $position ] ?? null ) ) {
			++$position;
		}

		// An array that was never closed leaves the cursor on a token no part
		// of this knows how to consume -- `[` and then the end of the block,
		// which happens whenever a documentation fence is a fragment. Without
		// this the loop appends nothing for ever and the tool dies on memory
		// rather than skipping the block.
		if ( $position === $before ) {
			throw new UnexpectedValueException( 'Unclosed array.' );
		}
	}
}

/**
 * Read one entry of an array, constant or not.
 *
 * A documented configuration is mostly literals, but not entirely: a
 * `sanitize_callback` is a closure, an `options` is often a function that
 * queries something, and a `logo` is a plugins_url() call. None of them can
 * be read without running them, and none of them need to be -- what is being
 * checked is that the *key* is real, not what the value computes to.
 *
 * So a value that is not constant is stepped over and comes back as null,
 * which is what `unknown_keys()` wants anyway: the key is present, and its
 * value is nothing this tool has an opinion about.
 *
 * @param array<int, array{0: int, 1: string}|string> $tokens   The token stream.
 * @param int                                         $position Cursor, by reference.
 *
 * @return mixed The value, or null where it is not constant.
 */
function read_entry( array $tokens, int &$position ) {
	$start = $position;

	if ( ! starts_constant( $tokens, $position ) ) {
		return skip_expression( $tokens, $position );
	}

	$value = read_value( $tokens, $position );

	// The constant may be the first part of something larger: `'a' . $b`, or
	// `__( 'Save' ) . '!'`. If what follows is not an entry boundary then the
	// value read is not the entry's value, so the whole thing is stepped over.
	$next = $tokens[ $position ] ?? null;

	$ends = null === $next
		|| ',' === $next
		|| ']' === $next
		|| ')' === $next
		|| ';' === $next
		|| ( is_array( $next ) && T_DOUBLE_ARROW === $next[0] );

	if ( ! $ends ) {
		$position = $start;

		return skip_expression( $tokens, $position );
	}

	return $value;
}

/**
 * Whether the token at the cursor begins a constant.
 *
 * @param array<int, array{0: int, 1: string}|string> $tokens   The token stream.
 * @param int                                         $position Cursor.
 *
 * @return bool
 */
function starts_constant( array $tokens, int $position ): bool {
	$token = $tokens[ $position ] ?? null;

	if ( null === $token ) {
		return false;
	}

	if ( in_array( $token, [ '[', '-', '+' ], true ) ) {
		return true;
	}

	if ( ! is_array( $token ) ) {
		return false;
	}

	// A leading backslash on a global function.
	if ( T_NS_SEPARATOR === $token[0] ) {
		return starts_constant( $tokens, $position + 1 );
	}

	if ( in_array( $token[0], [ T_CONSTANT_ENCAPSED_STRING, T_LNUMBER, T_DNUMBER, T_ARRAY ], true ) ) {
		return true;
	}

	if ( T_STRING !== $token[0] ) {
		return false;
	}

	$name = strtolower( $token[1] );

	// A call is constant only if it is one of the translation functions;
	// anything else would have to be run to know its value.
	if ( '(' === ( $tokens[ $position + 1 ] ?? null ) ) {
		return in_array( $name, TRANSLATION_CALLS, true );
	}

	return in_array( $name, [ 'true', 'false', 'null' ], true );
}

/**
 * Step over an expression, stopping at the entry boundary.
 *
 * Balanced across every bracket kind, so a closure body, an argument list and
 * a nested array all pass by whole. Nothing in here is run.
 *
 * @param array<int, array{0: int, 1: string}|string> $tokens   The token stream.
 * @param int                                         $position Cursor, by reference.
 *
 * @return null Always, being the stand-in for a value that is not constant.
 */
function skip_expression( array $tokens, int &$position ) {
	$depth = 0;
	$count = count( $tokens );

	for ( ; $position < $count; $position++ ) {
		$token = $tokens[ $position ];

		// `{$name}` inside a double-quoted string opens with a token rather
		// than a bare brace, and closes with a bare one.
		if ( is_array( $token ) ) {
			if ( in_array( $token[0], [ T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ], true ) ) {
				++$depth;
			}

			continue;
		}

		if ( '(' === $token || '[' === $token || '{' === $token ) {
			++$depth;
			continue;
		}

		if ( ')' === $token || ']' === $token || '}' === $token ) {
			// The array's own closing bracket: the entry ended before it.
			if ( 0 === $depth ) {
				return null;
			}

			--$depth;
			continue;
		}

		if ( 0 === $depth && ( ',' === $token || ';' === $token ) ) {
			return null;
		}
	}

	return null;
}

/**
 * The value of a quoted string token.
 *
 * Only the escapes a configuration example uses. A double-quoted string
 * carrying an interpolation is not this token type, so it never arrives here.
 *
 * @param string $token The token text, quotes included.
 *
 * @return string
 */
function unquote( string $token ): string {
	$quote = $token[0];
	$body  = substr( $token, 1, -1 );

	if ( "'" === $quote ) {
		return str_replace( [ '\\\\', "\\'" ], [ '\\', "'" ], $body );
	}

	return str_replace(
		[ '\\\\', '\\"', '\\n', '\\t', '\\r', '\\$' ],
		[ '\\', '"', "\n", "\t", "\r", '$' ],
		$body
	);
}

/**
 * Pull the field configurations out of a block and read their values.
 *
 * The call shapes differ across the libraries — a taxonomy name and a config,
 * a list of post types and a config, or a single config on its own — so every
 * top-level array literal in a register_*() call is read and the ones
 * carrying `fields`, `panels` or `steps` are kept. Nothing is guessed at from
 * position.
 *
 * @param string $code A fenced PHP block.
 *
 * @return array<int, array<string, mixed>>
 */
function configs_in( string $code ): array {
	global $only_calls;

	$found = [];

	// A registration call, or a constructor. The kit's own examples build a
	// FieldSet directly — it is the layer underneath the register_*()
	// functions, so it has none of its own.
	if ( ! preg_match_all( '/(?:register_[a-z_]+|new\s+[A-Z][A-Za-z]*)\s*\(/', $code, $calls, PREG_OFFSET_CAPTURE ) ) {
		return $found;
	}

	// A README shows more than its own library. The term-fields and
	// user-fields ones both demonstrate pairing with wp-register-columns, in
	// the same fenced block, and a columns configuration is a map of names to
	// arrays carrying a `label` — indistinguishable from a field map by shape
	// alone. Read as fields, its `display_callback` and `sortable` came back
	// as keys nothing reads, when what nothing reads them is *this* library.
	//
	// So a library can say which calls register its fields.
	if ( [] !== $only_calls ) {
		$calls[0] = array_values(
			array_filter(
				$calls[0],
				static fn( $call ) => in_array(
					// `new FieldSet(` is named `FieldSet` on the command line.
					preg_replace( '/^new\s+|\s*\($/', '', (string) $call[0] ),
					$only_calls,
					true
				)
			)
		);
	}

	$length = strlen( $code );

	foreach ( $calls[0] as $call ) {
		// Walk the argument list, reading each array literal at depth one.
		$i     = (int) $call[1] + strlen( (string) $call[0] );
		$depth = 0;

		for ( ; $i < $length; $i++ ) {
			$character = $code[ $i ];

			if ( ')' === $character && 0 === $depth ) {
				break;
			}

			if ( '[' !== $character ) {
				continue;
			}

			// The whole literal, brackets balanced.
			$open = $i;

			for ( $depth = 0; $i < $length; $i++ ) {
				if ( '[' === $code[ $i ] ) {
					++$depth;
				} elseif ( ']' === $code[ $i ] ) {
					--$depth;

					if ( 0 === $depth ) {
						break;
					}
				}
			}

			$literal = substr( $code, $open, $i - $open + 1 );

			try {
				// Documentation is full of placeholders — `callable`,
				// `/* ... */`, `$my_callback` — that are prose rather than
				// code, and a literal that is not constant is skipped rather
				// than reported: this checks what a reader would copy, not
				// what they would read.
				$value = literal_value( $literal );
			} catch ( \Throwable $error ) {
				continue;
			}

			if ( is_array( $value ) && ( isset( $value['fields'] ) || isset( $value['panels'] ) || isset( $value['steps'] ) ) ) {
				$found[] = $value;
			} elseif ( is_field_map( $value ) ) {
				// Four of the five libraries take the field map as an argument
				// of its own rather than under a `fields` key, so a bare map
				// is a configuration too.
				$found[] = [ 'fields' => $value ];
			}

			$depth = 0;
		}
	}

	return $found;
}

/**
 * Whether an array is a map of field keys to field configurations.
 *
 * Told apart from a metabox configuration by its values: every one is an
 * array that names a type or carries a label. An options list fails on the
 * first count, and a metabox configuration on the second.
 *
 * @param mixed $value A candidate.
 *
 * @return bool
 */
function is_field_map( mixed $value ): bool {
	if ( ! is_array( $value ) || [] === $value ) {
		return false;
	}

	foreach ( $value as $key => $config ) {
		if ( ! is_string( $key ) || ! is_array( $config ) ) {
			return false;
		}

		if ( ! isset( $config['type'] ) && ! isset( $config['label'] ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Walk a configuration and check every field in it.
 *
 * @param array<string, mixed> $fields   Field configuration, keyed by field key.
 * @param Registry             $registry The type registry.
 * @param string               $where    A label for messages.
 *
 * @return string[] Problems found.
 */
function check_fields( array $fields, Registry $registry, string $where ): array {
	global $extra_types, $extra_keys;

	$problems = [];

	foreach ( $fields as $key => $config ) {
		if ( ! is_array( $config ) ) {
			continue;
		}

		$type = (string) ( $config['type'] ?? 'text' );

		// A type the consuming library draws itself. Its keys are its own, so
		// there is nothing here to check them against.
		if ( in_array( $type, $extra_types, true ) ) {
			continue;
		}

		if ( ! $registry->has( $type ) ) {
			$problems[] = sprintf( '%s: "%s" has type "%s", which is not registered.', $where, $key, $type );
			continue;
		}

		$field   = new Field( (string) $key, $registry->get( $type ), $config, null );
		$unknown = array_values( array_diff( $field->unknown_keys(), $extra_keys ) );

		if ( [] !== $unknown ) {
			$problems[] = sprintf(
				'%s: "%s" (%s) sets %s, which nothing reads.',
				$where,
				$key,
				$type,
				implode( ', ', $unknown )
			);
		}

		// Nested fields — a group, a repeater, an email editor.
		if ( isset( $config['fields'] ) && is_array( $config['fields'] ) ) {
			$problems = array_merge( $problems, check_fields( $config['fields'], $registry, $where . ' › ' . $key ) );
		}
	}

	return $problems;
}

$registry = new Registry();
$problems = [];
$checked  = 0;

foreach ( $files as $file ) {
	if ( ! file_exists( $file ) ) {
		fwrite( STDERR, sprintf( "%s does not exist.\n", $file ) );
		exit( 1 );
	}

	foreach ( fenced_php( (string) file_get_contents( $file ) ) as [ $line, $code ] ) {
		foreach ( configs_in( $code ) as $config ) {
			$fields = $config['fields'] ?? null;

			// A tabbed metabox puts its fields inside panels, and a setup
			// wizard inside steps. Both are a list of named groups with a
			// `fields` map in each.
			foreach ( [ 'panels', 'steps' ] as $container ) {
				if ( null !== $fields || ! isset( $config[ $container ] ) || ! is_array( $config[ $container ] ) ) {
					continue;
				}

				$fields = [];

				foreach ( $config[ $container ] as $group ) {
					$fields = array_merge( $fields, (array) ( $group['fields'] ?? [] ) );
				}
			}

			if ( ! is_array( $fields ) ) {
				continue;
			}

			$checked += count( $fields );
			$problems = array_merge( $problems, check_fields( $fields, $registry, sprintf( '%s:%d', $file, $line ) ) );
		}
	}
}

printf( "%d documented fields checked in %s\n", $checked, implode( ', ', $files ) );

if ( [] === $problems ) {
	echo "every one of them is real\n";
	exit( 0 );
}

printf( "\n%d problem(s):\n", count( $problems ) );

foreach ( $problems as $problem ) {
	printf( "  %s\n", $problem );
}

exit( 1 );
