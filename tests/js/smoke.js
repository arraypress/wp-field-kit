/**
 * Smoke test for the field kit's script.
 *
 * The PHP suite renders markup and never executes a line of JavaScript, so a
 * ReferenceError in a module is invisible to it. That is not hypothetical:
 * `config` was resolved in one IIFE and used in another, and the resulting
 * throw stopped the init loop dead — every module after it silently never
 * ran, and the only symptom was that several fields "didn't work".
 *
 * This loads the script against a minimal DOM and calls every module's init,
 * which is the path that broke.
 */

'use strict';

const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );

const source = fs.readFileSync(
	path.join( __dirname, '..', '..', 'assets', 'js', 'field-kit.js' ),
	'utf8'
);

/**
 * The smallest DOM the script touches.
 *
 * Deliberately minimal rather than jsdom: the point is to catch a reference
 * that does not resolve, not to assert behaviour, and a real DOM would drag
 * in a dependency this library otherwise does not have.
 */
function makeElement() {
	const element = {
		dataset: {},
		classList: { add() {}, remove() {}, contains: () => false },
		style: {},
		hidden: false,
		disabled: false,
		value: '',
		id: '',
		children: [],
		addEventListener() {},
		removeEventListener() {},
		querySelector: () => null,
		querySelectorAll: () => [],
		closest: () => null,
		getAttribute: () => null,
		setAttribute() {},
		hasAttribute: () => false,
		removeAttribute() {},
		appendChild() {},
		insertBefore() {},
		remove() {},
		focus() {},
		dispatchEvent() {},
		cloneNode() { return makeElement(); },
	};

	return element;
}

const documentStub = {
	readyState: 'complete',
	currentScript: { id: 'field-kit-js' },
	addEventListener() {},
	createElement: () => makeElement(),
	querySelector: () => null,
	querySelectorAll: () => [],
	body: makeElement(),
	ownerDocument: null,
};

const context = {
	document: documentStub,
	console,
	setTimeout,
	clearTimeout,
	fetch: () => Promise.resolve( { ok: true, json: () => Promise.resolve( {} ) } ),
	CSS: { escape: ( value ) => String( value ) },
	Event: function ( type ) { this.type = type; },
	URL,
	Promise,
	navigator: { clipboard: null },
	location: { origin: 'https://example.test' },
};

context.window = context;
context.window.addEventListener = () => {};
context.window.removeEventListener = () => {};
context.window.setTimeout = setTimeout;
context.window.clearTimeout = clearTimeout;
context.window.isSecureContext = true;
context.window.location = context.location;
context.window.ArrayPressFieldKit = {
	'field-kit': { restUrl: 'https://example.test/wp-json/field-kit/v1/', restNonce: 'x', i18n: {} },
};

let failures = 0;

try {
	vm.createContext( context );
	vm.runInContext( source, context, { filename: 'field-kit.js' } );
} catch ( error ) {
	console.error( `  script threw while loading: ${ error.message }` );
	process.exit( 1 );
}

const modules = context.window.ArrayPressFieldKitModules;

if ( ! modules ) {
	console.error( '  the script exposed no modules' );
	process.exit( 1 );
}

const expected = [
	'Conditions', 'Range', 'Toggle', 'Clipboard', 'Combobox', 'Reorder',
	'Gallery', 'Repeater', 'Media', 'Tags', 'CodeEditor', 'ColorPicker',
	'ActionButton',
];

expected.forEach( ( name ) => {
	if ( ! modules[ name ] ) {
		console.error( `  ${ name }: not exposed` );
		failures ++;

		return;
	}

	if ( typeof modules[ name ].init !== 'function' ) {
		return;
	}

	try {
		modules[ name ].init( documentStub );
	} catch ( error ) {
		console.error( `  ${ name }.init threw: ${ error.message }` );
		failures ++;
	}
} );

// The bootstrap's own loop, which is where the ReferenceError surfaced.
try {
	modules.init( documentStub );
} catch ( error ) {
	console.error( `  init() threw: ${ error.message }` );
	failures ++;
}

if ( failures ) {
	console.error( `\n${ failures } failure(s)` );
	process.exit( 1 );
}

console.log( `  ${ expected.length } modules loaded and initialised cleanly` );
