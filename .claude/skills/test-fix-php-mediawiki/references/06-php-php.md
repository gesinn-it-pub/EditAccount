**Coding Conventions — PHP**

**File structure**

- Every file starts with `declare( strict_types=1 );`

- No closing `?>` tag

- One class per file; filename matches class name (UpperCamelCase, e.g.
  `MyClass.php`)

**Namespaces and autoloading**

- PSR-4 via Composer (`autoload.psr-4` in `composer.json`)

- Acronyms treated as single words: `HtmlId`, not `HTMLId`

**Naming**

| Element                     | Convention     | Example            |
|-----------------------------|----------------|--------------------|
| Classes, interfaces, traits | UpperCamelCase | `PageFormParser`   |
| Methods, variables          | lowerCamelCase | `getFormContent()` |
| Constants                   | UPPER_CASE     | `MAX_FORM_SIZE`    |

**Type system**

- Use native type declarations on all parameters, properties, and return
  types

- PHPDoc only when native types are insufficient (e.g. `string[]`,
  `array<string, Foo>`)

- Nullable parameters: `?Type`, not `Type $x = null`

- Prefer `??` (null coalescing) and `??=` over ternary isset checks

- Use arrow functions `fn( $x ) => $x * 2` for single-expression
  closures

**Modern PHP features (target: PHP 8.1+)**

- Constructor property promotion

- `readonly` properties for immutable value objects

- `enum` instead of class constant groups

- `match()` instead of `switch` when returning a value

**Retrofitting native types onto existing, untyped code**

When adding a native parameter or return type to a method that
previously had none (or only a PHPDoc hint), do not trust the first
plausible-looking type — verify it against the method’s actual behavior,
not just its common case:

- Check every overriding/implementing method across subclasses agrees
  with the type you’re about to add. A tool or codemod that infers types
  from a base method’s body does not automatically fix sibling
  overrides, and a mismatched override is a fatal at call time, not a
  lint warning.

- Check every real caller, not just the common one. A parameter that is
  sometimes passed a sentinel value (`false` for "not found", `null` for
  "unset") alongside its normal type needs a union type (`array|false`),
  not the narrower type that only fits the common call site.

- Run the affected tests after adding the type. A mock with no stubbed
  return value returns `null` by default — this was silently tolerated
  by an untyped or nullable signature and becomes a hard `TypeError` the
  moment the signature gains a non-nullable native type, which is a
  reliable way to discover a path the test suite never exercised for
  real.

- Once a native type makes a PHPDoc `@param`/`@return` tag redundant,
  delete the tag — keep PHPDoc only for what a native type cannot
  express (element/key shape such as `@return array<string, Foo>`).

**Constructor promotion and `readonly` — when not to apply them**

Do not promote or mark `readonly` a property that is reassigned anywhere
after construction (a `reset()`/`clear()`-style method, a setter, a
value swapped mid-lifecycle) — `readonly` on such a property is not a
style preference, it changes behavior by making the reassignment a fatal
error. Promote only properties that are pure constructor-injected
dependencies.

A test that builds a partial mock with a constructor-skipping option
(e.g. `disableOriginalConstructor()`) will fail with an initialization
error against a promoted property, because promotion ties the property’s
assignment to the constructor body — a mock that skips the constructor
never assigns it. When this happens, do not add a suppression or make
the property nullable to work around it; revert that specific property
to a conventional declaration with a default value instead.

**Code style**

- Indentation: tabs, not spaces

- 1TBS brace style — opening brace on same line, `else`/`elseif` on
  closing brace line

- Always use braces, even for single-line blocks

- Spaces inside parentheses: `getFoo( $bar )`, empty: `getBar()`

- Spaces around binary operators: `$a = $b + $c`

- Single quotes preferred; double quotes for string interpolation

- `===` strict equality; `==` only when type coercion is intentional

- No Yoda conditions: `$a === 'foo'`, not `'foo' === $a`

- `elseif` not `else if`

- `true`, `false`, `null` always lowercase

**Architecture**

- `private` by default; `protected` only when subclass access is needed

- Dependency injection over direct instantiation — delegate `new Foo()`
  to factories

- Single Responsibility: one class, one concern

- Order class members: `public` → `protected` → `private`

**Deprecation handling**

Treat deprecation warnings as errors — they are signals of technical
debt that must be resolved, not suppressed.

Configure `phpunit.xml` to convert `E_USER_DEPRECATED` to a test
failure:

``` xml
<phpunit convertDeprecationsToExceptions="true">
    ...
</phpunit>
```

When a test triggers a deprecation from code under your control, fix the
code to use the non-deprecated API. When the deprecation originates from
a third-party dependency outside your control, suppress it at the call
site with a comment:

``` php
// @deprecated-call: Foo::bar() deprecated in lib 2.3, remove when lib ≥ 3.0 is required
@trigger_error( '', E_USER_DEPRECATED );  // suppress in test output
$result = Foo::bar();
```

Never use `@` suppression without an explanatory comment and a removal
condition.

**Deleting long-deprecated public API**

Before deleting a public method or function that has been deprecated for
a while, verify and state explicitly which of these applies — a reviewer
needs this evidence to trust the deletion is safe, not just the fact
that it was marked deprecated:

- Zero remaining callers anywhere in the codebase (grep for the symbol
  name).

- Callers exist only in tests — update them to the replacement API in
  the same commit as the deletion.

- PHP method-name lookups are case-insensitive; a plain-text grep for
  the exact declared casing can miss a caller that invokes the method
  with different capitalization. Cross-check with a case-insensitive
  search when the symbol is a commonly-cased word.

Group deletions by the version they were deprecated in (or by unrelated
concern) into separate commits rather than one sweeping "remove old
code" commit — this keeps each deletion’s blast radius easy to reason
about and to revert independently if one turns out to have a missed
caller.

**Type safety at variable-shape boundaries**

Never destructure, array-index, or iterate a value whose shape depends
on a runtime branch without confirming that shape first. Older PHP
versions often coerce the mismatch silently (e.g. list-assigning a
non-array yields `null` in every target, `(int)` casting a non-numeric
string yields `0`); newer PHP versions increasingly turn the same
mismatch into a fatal `TypeError`. Code that "works" only because of a
silent coercion is already broken — it is one PHP or dependency upgrade
away from a fatal, and the fix at that point is identical to doing it
now.

``` php
// ANTI-PATTERN: a helper that sometimes returns early (prints output, sends a
// response) and otherwise returns a result tuple. Destructuring unconditionally
// silently assigned null on old PHP; on new PHP it fatals with a TypeError.
[ $result, $meta ] = $this->fetchOrEmit( $request );

// CORRECT: confirm the shape before destructuring.
$fetched = $this->fetchOrEmit( $request );
if ( is_array( $fetched ) ) {
    [ $result, $meta ] = $fetched;
}
```

Apply the same guard-before-use discipline to: array offsets on a value
that may be `null` or scalar, `foreach` over a value that may not be
iterable, and arithmetic on a value that may be a non-numeric string.
