# WordPress Field Kit

One field layer for the WordPress admin.

A field is a configuration array. This turns it into a control, reads its value from wherever that value lives, writes
it back sanitized, and declares it to WordPress. Where the control appears — a settings page, a metabox, a term screen,
a user profile, a quick-edit row, a slide-out panel — is the caller's business, not the field's.

It exists because five libraries had each written that layer, and they had drifted. The same `select2` field was a
searchable dropdown in one and a plain one in another; the same relational field had three different search endpoints;
"empty" meant something different in each. This is the one answer, and the libraries that used to hold their own are
now thin things that know about screens.

## Features

- 56 field types, from `text` to a nested repeater to a merge-tag email editor
- 10 storage contexts: options, post/term/user/comment meta, a plain array, an object, constants, encryption
- One REST search endpoint behind every relational and callback-backed field
- One REST action endpoint behind every button
- Conditional fields, evaluated in the browser and properly hidden
- Configuration keys are declared, so one nothing reads warns under `WP_DEBUG` rather than doing nothing quietly
- Core's own classes and colours throughout — a panel follows the user's admin scheme

## Requirements

- PHP 7.4+
- WordPress 5.8+

## Installation

```bash
composer require arraypress/wp-field-kit
```

Most people will not install this directly. It is what
[wp-register-setting-fields](https://github.com/arraypress/wp-register-setting-fields),
[wp-register-post-fields](https://github.com/arraypress/wp-register-post-fields),
[wp-register-term-fields](https://github.com/arraypress/wp-register-term-fields),
[wp-register-user-fields](https://github.com/arraypress/wp-register-user-fields),
[wp-register-quick-edit-fields](https://github.com/arraypress/wp-register-quick-edit-fields),
[wp-register-bulk-edit-fields](https://github.com/arraypress/wp-register-bulk-edit-fields) and
[wp-register-flyouts](https://github.com/arraypress/wp-register-flyouts) are built on.

## The three pieces

A **field set** is a group of fields bound to one **context**, and a **type** knows how to draw and clean one value.

```php
use ArrayPress\FieldKit\FieldSet;
use ArrayPress\FieldKit\Context\OptionContext;

$set = new FieldSet(
    [
        'site_name' => [ 'type' => 'text', 'label' => __( 'Site name', 'my-plugin' ) ],
        'per_page'  => [ 'type' => 'number', 'label' => __( 'Per page', 'my-plugin' ), 'min' => 1, 'max' => 100 ],
    ],
    new OptionContext( 'my_plugin' ),
    'my_plugin'
);

// Render.
foreach ( $set->fields() as $field ) {
    echo $set->render_field( $field );
}

// Save. Each value is coerced by its own type; a key you did not declare is dropped.
$set->save( $_POST['my_plugin'] ?? [] );
```

The third argument is the input prefix: what the form submits under, and what the set's REST names are scoped by.

## Contexts

Where the values live. A field does not know or care.

| Context | Reads and writes |
| --- | --- |
| `OptionContext` | One option, holding an array of values. |
| `PostMetaContext` | Post meta. |
| `TermMetaContext` | Term meta. |
| `UserMetaContext` | User meta. |
| `CommentMetaContext` | Comment meta. |
| `ArrayContext` | A plain array — useful in tests and for a form that is not stored yet. |
| `ObjectContext` | An object: a BerlinDB row, a `WP_Post`, a `stdClass`. Writes collect for you to persist. |
| `ConstantContext` | Wraps another; a defined constant wins over the stored value. |
| `EncryptedContext` | Wraps another; named fields are encrypted at rest. |

`ObjectContext` looks for each value as `{key}_data()`, then `get_{key}()`, then a property — so most objects work
without being told anything. Its writes are collected rather than applied, because setting properties on somebody
else's object is not something a field library should do.

## Field types

Text and numeric
: `text` `email` `url` `tel` `password` `hidden` `number` `range` `textarea` `code` `wysiwyg` `code_generator`

Date, time and colour
: `date` `time` `datetime` `color` `gradient` `date_range` `time_range`

Choice
: `select` `enhanced_select` `select_multiple` `checkbox` `toggle` `radio` `checkbox_group` `button_group` `card_choice`

Relational — one search endpoint behind all of them
: `post` `page` `user` `taxonomy` `ajax` `tags`

Media
: `image` `file` `file_url` `gallery` `files`

Compound
: `group` `repeater` `list` `key_value` `sortable` `providers` `dimensions` `amount_type`

Layout and display
: `heading` `separator` `message` `html` `link` `clipboard` `oembed` `custom`

Purpose-built
: `license` `email_editor` `action_button`

### Providers

A list of things another plugin registered: put in order, switched on, and
each configured on its own. Payment gateways are the shape everybody
recognises — the order is the order they appear at checkout — but nothing in
the field knows the word, so shipping methods, tax providers and storage
backends are the same list with different names in it.

```php
'gateways' => [
    'type'      => 'providers',
    'label'     => 'Payment gateways',

    // An array, or a callable resolved when the field is drawn — which is
    // what a registry that fills up on `init` needs.
    'providers' => 'my_plugin_get_gateways',

    // Where a provider's own settings go: 'inline' opens the row, 'flyout'
    // opens a dialog. Inline suits two API keys; a flyout suits twenty.
    'config'    => 'inline',
],
```

Each provider is a label and, optionally, fields of its own:

```php
[
    'stripe' => [
        'label'       => 'Stripe',
        'description' => 'Cards, wallets and local methods.',
        'logo'        => plugins_url( 'stripe.svg', __FILE__ ),
        'fields'      => [
            'secret_key' => [ 'type' => 'password' ],
        ],
    ],

    // A bare string is a provider with nothing to configure, and it gets no
    // Configure button — a control that opens an empty panel is worse than
    // no control.
    'cod'    => 'Cash on delivery',
]
```

It stores three keys, because the two questions asked of it at runtime are
"which are on, in what order" and "what is this one's configuration", and
both are a lookup in this shape and a scan in any other:

```php
[
    'order'    => [ 'paypal', 'stripe' ],
    'enabled'  => [ 'stripe' ],
    'settings' => [ 'stripe' => [ 'secret_key' => '…' ] ],
]
```

A provider registered after a value was stored is appended rather than
dropped, so installing an add-on does not add a gateway nobody can find; and
an id the form offers that nobody registered is refused on save.

Older spellings still resolve: `select2`, `ajax_select`, `switch`, `colour`, `term`, `hr`, `notice`, `post_ajax`,
`taxonomy_ajax`, `user_ajax`.

## Searching

Any field with a `search_callback` gets a searchable combobox and an endpoint to answer it. The callback answers both
questions the field asks — what matches what was typed, and what the saved values are called:

```php
'customer' => [
    'type'            => 'ajax',
    'label'           => __( 'Customer', 'my-plugin' ),
    'search_callback' => function ( string $term, ?array $ids = null ): array {
        $args = $ids ? [ 'include' => $ids ] : [ 'search' => '*' . $term . '*' ];

        $found = [];

        foreach ( get_users( $args + [ 'number' => 20 ] ) as $user ) {
            $found[ $user->ID ] = $user->display_name;
        }

        return $found;
    },
],
```

Registration happens when the set is built, not when the field renders — the request that searches is not the request
that drew the control.

For posts, terms and users there is nothing to write: those types search themselves.

## Buttons

A field can name handlers, which are registered the same way and reached over the same kind of endpoint:

```php
'licence' => [
    'type'    => 'license',
    'label'   => __( 'Licence key', 'my-plugin' ),
    'actions' => [
        'activate'   => fn( $request ) => activate( $request['value'] ),
        'deactivate' => fn( $request ) => deactivate( $request['value'] ),
    ],
],
```

A handler returns `[ 'success' => bool, 'message' => string ]`, and the message is announced in the field's own live
region. It may also return `data.html`, which is written into a preview area — that is how the email editor shows what
it would send.

### Reporting a new state

A licence handler should also return `data.state`, because activating a licence changes what the field *is*, not only
what it has to say:

```php
return [
    'success' => true,
    'message' => __( 'Licence activated.', 'my-plugin' ),
    'data'    => [
        'state' => [
            'active' => true,
            'key'    => $masked,
            'sites'  => [ 1, 3 ],
        ],
    ],
];
```

The field then swaps its badge, its seat count and its button — Activate becomes Deactivate, and posts to the other
handler — without a page load. Without it the activation succeeds, the message says so, and the badge beside it still
reads "Not active": two contradictory answers on the same row, with a reload the only way to find out which is true.

`key` is what the box should show afterwards, and it is the handler's decision because only the handler knows. The
field masks a stored key when it renders one, so returning the masked form on activation and the whole key on
deactivation is what makes the live state match what a reload would give.

## Conditional fields

```php
'fields' => [
    'mode'    => [ 'type' => 'select', 'options' => [ 'auto' => 'Auto', 'manual' => 'Manual' ] ],
    'api_key' => [ 'type' => 'password', 'depends' => [ 'mode' => 'manual' ] ],
],
```

Several shorthands are accepted — a field name alone for "truthy", a key/value pair, or a full
`[ 'field' => ..., 'value' => ..., 'operator' => ... ]`. A hidden field is removed from the tab order, not merely made
invisible.

## Configuration keys are declared

Every type says which keys it reads. One that nothing reads is not an error in PHP — the array entry sits there and the
control renders with its defaults — so the kit warns instead, under `WP_DEBUG`:

> The field "products" (repeater) was given configuration nothing reads: button_label.

A consuming library adds its own with `Field::allow_config_keys( [ 'tab', 'section' ] )`.

There is also a checker for documentation, since an example is where a wrong key usually lives:

```bash
php vendor/arraypress/wp-field-kit/bin/verify-docs.php --calls=register_my_thing README.md
```

## Support classes

Small pieces the consuming libraries share rather than each writing:

| Class | What it is |
| --- | --- |
| `Support\Button` | One button, in core's classes. Two weights and a destructive treatment, which is all core has. |
| `Support\Display` | Turning a stored value into something to read, and deciding what "empty" means. |
| `Support\Badge` | A status pill. |
| `Support\PageHeader` | A page heading with a logo, tabs and actions. |
| `Support\PanelTabs` | A vertical tab list, with the ARIA pattern done. |
| `Support\Tooltip` | A help bubble beside a label. |
| `Support\MergeTags` | The tags an email editor offers, and their replacement. |
| `Support\Resolve` | Finding a named value on an object or an array. |
| `Support\Markup` | Escaping helpers with one policy behind them. |

## Adding a type

```php
use ArrayPress\FieldKit\Registry;
use ArrayPress\FieldKit\Types\AbstractInputType;

final class ColourNameType extends AbstractInputType {

    public function id(): string {
        return 'colour_name';
    }

    protected function input_type(): string {
        return 'text';
    }
}

( new Registry() )->register( new ColourNameType() );
```

## Testing

```bash
composer test        # PHPUnit
composer test:js     # the browser modules, in Node
composer lint        # WordPress coding standards
composer format:check
```

The suite includes a few guards that exist because green tests once coexisted with a broken page: a stylesheet test
that reads the CSS for rules that fight the markup, a coverage test asserting every registered type is reachable, an
accessibility test that every type is labelled, and a self-reference test that every `self::` in the library resolves.

## License

GPL-2.0-or-later
