**Coding Conventions — PHP · MediaWiki**

Tooling:
[mediawiki-codesniffer](https://github.com/wikimedia/mediawiki-tools-codesniffer)
via PHPCS. Run locally: `make composer-phpcs` (or `make ci`).

**Source directories**

- New code belongs in `src/` following PSR-4; `includes/` is legacy and
  should be migrated incrementally

**Namespaces**

- Top-level namespace = extension name (e.g.
  `MediaWiki\Extension\FooBar...`)

**Global variable prefix**

- Global variables: `$wg` prefix (e.g. `$wgPageFormsSettings`)

**Request handling**

- No superglobals (`$_GET`, `$_POST`) — use `WebRequest` via
  `RequestContext`

- No new global functions — use static utility classes (`Html`, `IP`) if
  needed

**Version guards**

Use version guards to call the correct API across supported MediaWiki
versions while preventing deprecation warnings.

``` php
if ( version_compare( MW_VERSION, '1.42', '>=' ) ) {
    $html = $parserOutput->getRawText();
} else {
    // MW < 1.42: getRawText() did not exist; getText() was the only option
    $html = $parserOutput->getText();
}
```

Rules:

- Use `MW_VERSION` — never read `$wgVersion` directly

- Use `version_compare()` — never compare version strings with `===` or
  `>=` operators

- Write the guard condition so the **new** (non-deprecated) path is the
  `if`-branch

- Add a comment on the `else`-branch naming the deprecated call and the
  minimum version that removes the guard

- Name version boundaries with the **first** version that ships the new
  API, not the last that ships the old one

**Removing version guards**

When support for a MediaWiki version is dropped:

1.  Search for all guards referencing that version:  
    `grep -rn "version_compare.MW_VERSION.'1.XX'" src/ includes/`

2.  Delete the entire `if/else` block and keep only the `if`-branch body
    (the new path)

3.  Delete any `@deprecated-call` comments that referenced the dropped
    version

4.  Run the full test suite and linters to confirm nothing regressed

**Multi-backend database access**

An extension that supports more than one DB backend (MySQL/MariaDB,
PostgreSQL, SQLite) must not assume every backend behaves identically
for anything beyond standard SQL. Apply these rules whenever writing or
reviewing raw SQL or schema DDL:

- Never interpolate a table or column name into a query string directly,
  even when it comes from trusted-looking schema metadata — use the DB
  abstraction’s identifier-quoting method (e.g.
  `IDatabase::addIdentifierQuotes()`). It produces the correct quote
  character per backend and fails fast with a typed exception on
  malformed input, instead of emitting SQL that only works on the
  backend you tested against.

- Binary or specially-collated values (hashes, sort keys) are not
  portable across backends by default — wrap encode/decode at the
  boundary with a backend-aware helper, not inline per call site, so a
  new call site cannot forget the conversion.

- Prefer capability detection over version/backend branching: check
  whether the connection supports the feature you need (e.g. a specific
  query construct) rather than hardcoding "if Postgres, do X" — and keep
  an iterative fallback path for backends that lack it.

- `INSERT …​ SELECT` against a live table takes locks the caller may not
  expect (shared or exclusive, depending on backend and isolation
  level). For a read-only snapshot under concurrent write load, prefer
  reading into the application layer and inserting literals over relying
  on the database to combine both in one statement.

- When a fix targets one backend’s failure, add a regression test
  asserting the **other** supported backends are unaffected, not only
  that the failing backend now passes — a cross-backend fix that only
  special-cases the broken path can silently change behavior everywhere
  else.
