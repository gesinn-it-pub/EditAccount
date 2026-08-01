**Coding Conventions — HTML Output · MediaWiki**

MediaWiki extensions must never concatenate raw HTML strings with
unescaped variables. Use the two sanctioned approaches below depending
on complexity.

**`Html` class — simple elements and inputs**

Use [`Html`](https://www.mediawiki.org/wiki/Manual:Html.php) for
individual elements, form inputs, and attribute construction.
`Html::element()` escapes both attribute values and text content
automatically. `Html::rawElement()` escapes attribute values but passes
the inner HTML through unchanged — use only when the content is already
safe.

This is explicitly required by [Best practices for
extensions](https://www.mediawiki.org/wiki/Best_practices_for_extensions):
"MUST: Use MediaWiki’s validation/sanitization methods, e.g. those in
the `Html` and `Sanitizer` classes."

``` php
// Safe: both attributes and text content are escaped
$html = Html::element( 'span', [ 'class' => 'pf-date-input__error' ], $userText );

// Safe: attributes escaped; $innerHtml must already be safe
$html = Html::rawElement( 'div', [ 'class' => 'pf-date-input' ], $innerHtml );

// Form inputs
$html = Html::hidden( 'wpEditToken', $token );
$html = Html::input( 'q', $value, 'search', [ 'class' => 'pf-search__input' ] );
```

Anti-patterns to avoid:

- `'<span class="' . $class . '">' . $text . '</span>'` — no escaping,
  XSS risk

- Direct use of `$_GET` / `$_POST` in HTML — use `WebRequest` first

**`isHTML` return values — every branch must resolve to safe HTML**

Parser functions, tag hooks, and result printers can return
`[ $text, 'isHTML' ⇒ true ]` (or set
`$parser→getOutput()`/`ParserOutput::setText()` equivalents) to tell
MediaWiki the string is already-final HTML. This is a stronger claim
than
`` Html::rawElement()’s "this fragment is safe": it tells the parser to skip wikitext processing for the entire returned value, via a strip marker that is spliced back in verbatim near the end of parsing. Nothing sanitizes an `isHTML ``
string afterwards — if it contains unparsed wikitext or a
user-controlled value, that reaches the page as-is.

This means every conditional branch that can produce the returned string
must independently guarantee it is fully resolved to HTML. A guard that
only sometimes runs the resolving step is not a performance shortcut —
it is an unparsed-output leak, and if any value it touches is
user-authored (a template parameter, a query parameter such as a
search/caption label, wikitext from a transcluded page), it is stored or
reflected XSS.

``` php
// ANTI-PATTERN: the guard's false branch skips parsing, but isHTML still claims
// the whole string is safe. $link may still contain raw wikitext or, if built
// from a user-authored parameter, a raw <script> tag.
if ( !$this->isSpecialPage() ) {
    $link = $parser->recursiveTagParse( $link );
}
return [ $html . $link, 'isHTML' => true ];

// CORRECT: resolution happens unconditionally, so there is no branch where an
// unresolved value can reach the isHTML output.
$link = $this->recursiveTextProcessor->recursiveTagParse( $link );
return [ $html . $link, 'isHTML' => true ];
```

Before writing an ad-hoc call to `Parser::recursiveTagParse()` (which
requires an in-progress parse — it fatals otherwise) or a bare
`ParserFactory::getMainInstance()`, check whether the repository already
provides a text-processing helper for this exact situation. Frameworks
that inject query results into result printers commonly hand the printer
the **in-flight** parser together with a wrapper that checks whether a
parse is actually in progress and falls back to a full parse otherwise
(for example, `RecursiveTextProcessor` in Semantic MediaWiki-based
extensions, injected by the query processor). Reusing that wrapper is
both safer (no unparsed branch, no wrong-parser-instance bugs during
nested parses) and consistent with how the rest of the codebase already
does this — grep the codebase for how sibling result printers/classes
build similar links before inventing a new approach.

**`TemplateParser` + Mustache — complex HTML structures**

Use
[`TemplateParser`](https://www.mediawiki.org/wiki/Manual:TemplateParser)
for larger HTML blocks, conditional structures, and repeated elements.
Mustache templates (`.mustache` files) separate logic from markup and
are testable in isolation. This approach has been the recommended
standard since MediaWiki 1.25 (RFC: HTML templating library, approved
2014).

``` php
// In your class constructor or method:
$templateParser = new TemplateParser( __DIR__ . '/../templates' );
$html = $templateParser->processTemplate( 'MyWidget', [
    'label'    => $label,       // auto-escaped by Mustache {{label}}
    'items'    => $items,       // array of associative arrays for {{#items}} sections
    'isActive' => $isActive,
] );
```

``` text
{{! templates/MyWidget.mustache }}
<div class="pf-widget{{#isActive}} pf-widget--active{{/isActive}}">
    <label class="pf-widget__label">{{label}}</label>
    <ul class="pf-widget__list">
        {{#items}}<li class="pf-widget__item">{{name}}</li>{{/items}}
    </ul>
</div>
```

Rules:

- Store \*.mustache files under a `templates/` directory alongside your
  PHP source

- Use `{{variable}}` (HTML-escaped) by default; `{{{variable}}}`
  (unescaped) only for already-safe HTML

- Do not build HTML strings inside PHP and pass them into a Mustache
  variable unless the value is wrapped in `new HtmlArmor( $html )`

- Prefer Mustache for any structure with more than two or three nested
  elements

**Choosing between the two**

| Situation                                              | Use                                      |
|--------------------------------------------------------|------------------------------------------|
| Single element, input, or short attribute construction | `Html::element()` / `Html::rawElement()` |
| Form controls (hidden fields, inputs)                  | `Html::hidden()` / `Html::input()`       |
| Multi-element blocks, conditionals, loops              | `TemplateParser` + Mustache              |
| Skin or full-page templating                           | `SkinMustache`                           |

**Semantic CSS classes — customizability**

Every rendered component must carry CSS classes that allow site
administrators and users to target elements via `MediaWiki:Common.css`
or a site skin without reading extension source code. Use BEM
(Block\\\_Element--Modifier) with the extension-specific prefix defined
in `css.adoc`.

Levels:

- **Block** — the outermost container of a component:
  `{prefix}-{component}` (e.g. `pf-date-input`)

- **Element** — a named functional part of the block:
  `{prefix}-{component}__{part}` (e.g. `pf-date-input__label`,
  `pf-date-input__input`, `pf-date-input__help`)

- **Modifier** — a state or variant: `{prefix}-{component}--{state}`
  (e.g. `pf-date-input--disabled`, `pf-date-input--error`)

Rules:

- Every component wrapper receives a block class — no anonymous `<div>`
  containers

- Functional sub-elements (label, input, error message, help text, icon)
  each receive an element class

- States (disabled, required, error, loading) are expressed as modifier
  classes on the block, not via inline styles or ad-hoc class names

- Modifiers are added alongside the block class, not instead of it:
  `class="pf-date-input pf-date-input--error"`

- IDs are allowed for ARIA relationships (`aria-describedby`,
  `aria-labelledby`) but must not be the primary styling hook

Example:

``` text
<div class="pf-date-input pf-date-input--required">
    <label class="pf-date-input__label" for="pf-date-input-1">Start date</label>
    <input class="pf-date-input__input" id="pf-date-input-1" type="text" />
    <span class="pf-date-input__help">Format: YYYY-MM-DD</span>
    <span class="pf-date-input__error">Invalid date</span>
</div>
```

**Migration strategy for existing code**

Changing CSS class names on rendered HTML is a breaking change for any
site that targets those classes in `MediaWiki:Common.css` or a skin.
Apply the following strategy — do not rename classes silently.

| Code status                     | Rule             | Action                                                                               |
|---------------------------------|------------------|--------------------------------------------------------------------------------------|
| New code                        | BEM required     | Apply BEM from the start — no legacy class needed                                    |
| Existing code being modified    | Migrate on touch | Add BEM class, keep old class alongside it, document as deprecated in `CHANGELOG.md` |
| Existing code not being touched | Leave as-is      | Do not migrate speculatively — only when the file is opened for another reason       |

Deprecation procedure when migrating existing classes:

1.  Add the BEM class alongside the old class:  
    `class="pf-date-input pf-dateinput"` — old class last

2.  Add a `CHANGELOG.md` entry under `Deprecated`:  
    "CSS class `pf-dateinput` deprecated; use `pf-date-input` instead.
    Old class will be removed in the next major release."

3.  Remove the old class in the next major version bump and add a
    `Breaking Changes` entry to `CHANGELOG.md`

**Regression tests for an escaping fix — assert both directions**

A test that only checks the escaped form is present can pass for the
wrong reason (e.g. the payload happened to contain no special characters
that round-trip differently). A test that only checks the raw payload is
absent can pass because the output was mangled some other way, not
properly escaped. An escaping regression test asserts both in the same
case:

``` text
assert-output:
  to-contain:
    - "&lt;img src=x onerror=alert() &gt;"   // the escaped form is present
  not-contain:
    - "<img src=x onerror=alert() >"          // the raw payload never reached output
```

Cover every output mode/sink the value can reach independently (e.g.
inline wikitext vs. a raw/API/export mode that bypasses the parser) — a
sink that is parser-sanitised today is not evidence that a sibling sink
bypassing the parser is also safe. When auditing a reflected-input
surface (a query parameter, a pagination token, a form field echoed back
into an error message), check every branch that renders that value, not
just the one the report described.
