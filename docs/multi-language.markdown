# Multiple language support

Multi-language support is built into the framework core. It spans four areas that work together:

1. **Configuration** - the set of languages a site offers and its default;
2. **Language selection** - how the active language is chosen for each request;
3. **Language constants** - translatable strings stored in JSON files;
4. **Multi-language database fields** - columns that hold a separate value per language.

Throughout the framework the currently active language is available as the global `$language` (a two-letter
code such as `en`), and its text direction as the boolean `$language_rtl`.


## 1. Configuration

Two variables in the site `config.php` drive everything (defaults shown):

```php
// language configuration
$available_languages = array('en');
$default_language = 'en';
```

A multi-language site simply lists every offered language:

```php
$available_languages = array('en', 'de', 'fr', 'he');
$default_language = 'en';
```

There is no hard limit on the number of languages. Note that the framework's own constants are only translated
into a subset of languages (see the `system/language_*.json` files for the list shipped with the system).


## 2. Language selection

For every request the active language is resolved in `Language::apply_for_client()`:

- The language is taken from the `language` request parameter (`$_REQUEST['language']`). If it is missing or
  not part of `$available_languages`, the `$default_language` is used.
- On a visitor's **first** visit (detected by the absence of the `Caracal_LanguageMatched` cookie) the framework
  compares the browser's `Accept-Language` header against `$available_languages` and, if a better match than the
  current language is found, issues a `302` redirect to the same page in that language. A cookie is then set so
  this matching happens only once.
- `$language_rtl` is set according to whether the active language is written right-to-left.

### Language prefix in URLs

Section routing accepts an optional two-letter language prefix at the start of the path, for example
`/de/about` or `/fr/products/123`. When URLs are generated through `URL::make()` the prefix is added
automatically for every language **except** the default one, which is served without a prefix:

```
$default_language = 'en';

/about        ->  English (default, no prefix)
/de/about     ->  German
/fr/about     ->  French
```

The current language is also carried across links automatically, so navigation stays within the selected
language without any extra work in templates.


## 3. Language constants

Translatable strings are stored as flat JSON objects, one file per language, named `language_<code>.json`.
There are three locations, searched in order of increasing precedence:

- **System** - `system/language_<code>.json` (framework constants);
- **Module** - `modules/<name>/data/language_<code>.json` (per-module constants);
- **Site** - the site's data directory `language_<code>.json` (site-wide constants, override system ones).

Example `language_en.json`:

```json
{
	"site_title": "My Website",
	"menu_home": "Home",
	"menu_contact": "Contact us"
}
```

and its German counterpart `language_de.json`:

```json
{
	"site_title": "Meine Webseite",
	"menu_home": "Startseite",
	"menu_contact": "Kontakt"
}
```

When a language file is missing the framework falls back to the English (`en`) version and emits a warning.

### Resolving constants in code

- `Language::get_text($constant, $language = null)` returns the value for the current (or a specified) language.
  Site constants take precedence over system constants.
- Inside a module, `$this->get_language_constant($constant, $language = null)` resolves the constant from that
  module's own `data/language_*.json` files.

### Using constants in templates

The [`cms:text`](tags/text.markdown) tag prints a constant:

```xml
<title><cms:text constant="site_title"/></title>

<!-- constant defined by a specific module -->
<cms:text constant="label_add_to_cart" module="shop"/>

<!-- force a specific language regardless of the active one -->
<cms:text constant="menu_home" language="de"/>
```

Constants can also be injected into arbitrary attributes with the
[`cms:constant` and `cms:tooltip`](tags/attributes.markdown) directives:

```xml
<input type="submit" cms:constant="value" value="button_save"/>
<a href="/help" cms:tooltip="tooltip_help">?</a>
```


## 4. Multi-language database fields

Content that differs per language (article titles, page bodies, ...) is stored in **multi-language columns**.
Three field types are available:

| Type         | Underlying column |
|--------------|-------------------|
| `ML_VARCHAR` | `VARCHAR`         |
| `ML_TEXT`    | `TEXT`            |
| `ML_CHAR`    | `CHAR`            |

### Declaring the columns

In the table's SQL file the column is declared with the lower-case `ml_*` type. `Query` expands it into one real
column per configured language when the table is created:

```sql
CREATE TABLE `articles` (
	`id` int NOT NULL AUTO_INCREMENT,
	`title` ml_varchar(255) NOT NULL DEFAULT '',
	`content` ml_text NOT NULL,
	PRIMARY KEY (`id`)
);
```

With `$available_languages = array('en', 'de')` the actual table receives `title_en`, `title_de`, `content_en`
and `content_de` columns.

The matching item manager declares the field once, using the `ML_*` type:

```php
$this->add_property('title', 'ml_varchar');
$this->add_property('content', 'ml_text');
```

### Writing multi-language values

Pass an array keyed by language code. The manager expands it into the per-language columns automatically:

```php
$manager->insert_item(array(
		'title'   => array('en' => 'Hello', 'de' => 'Hallo'),
		'content' => array('en' => 'Welcome!', 'de' => 'Willkommen!')
	));
```

Updating works the same way.

### Reading multi-language values

`get_items()` / `get_single_item()` re-pack the per-language columns back into a single field that is an array
keyed by language code:

```php
$item = $manager->get_single_item($manager->get_field_names(), array('id' => 1));

echo $item->title['en'];   // "Hello"
echo $item->title[$language];  // value for the active language
```

To fetch only the active language's value directly, use `get_item_value()`, which selects the correct
per-language column for you:

```php
$title = $manager->get_item_value('title', array('id' => 1));  // active language only
```

### Displaying multi-language content in templates

A module usually passes the whole language array as a template parameter. Mark the tag as multi-language so the
active language is picked automatically:

```xml
<h1><cms:var param="title" multilanguage="yes"/></h1>

<!-- render Markdown body for the active language -->
<cms:markdown param="content" multilanguage="yes"/>
```

Without `multilanguage="yes"` the raw array would be printed, so the flag is required for `ML_*` parameters.


## 5. Helper functions

The `Language` helper class exposes a few useful methods:

- `Language::get_languages($printable = true)` - list of available languages, either as `code => full name`
  pairs (`printable`) or as a plain list of codes;
- `Language::get_printable($code)` - full human-readable name for a language code;
- `Language::is_rtl($language = null)` - whether the active (or specified) language is right-to-left;
- `Language::get_rtl()` - list of all right-to-left language codes;
- `Language::match_browser_language($supported, $default)` - best match between the browser's preferences and the
  supported languages.
