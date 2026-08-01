**Static Analysis — Phan · PHP**

Tooling: [Phan](https://github.com/phan/phan) with
[mediawiki-phan-config](https://github.com/wikimedia/mediawiki-phan-config).
Run locally: `make composer-phan` (or `make dev-test`).

**Setup**

Add the Phan script to `composer.json`:

``` json
"scripts": {
    "phan": "phan --allow-polyfill-parser"
}
```

<div class="note">

`--allow-polyfill-parser` activates a pure-PHP AST fallback. Required
when the native `php-ast` extension is not available (e.g. Debian trixie
/ PHP 8.3 where `php-ast` has no apt package). Without this flag Phan
exits immediately if `php-ast` is absent.

</div>

Add the following targets to the extension `Makefile`:

``` makefile
composer-phan: .init ## Run Phan static analysis
    $(compose-exec-wiki) bash -c "cd $(EXTENSION_FOLDER) && composer phan $(COMPOSER_PARAMS)"

composer-phan-update-baseline: .init ## Re-generate baseline and fix indentation for PHPCS
    $(compose-exec-wiki) bash -c "cd $(EXTENSION_FOLDER) && composer phan -- --save-baseline=.phan/baseline.php"
    unexpand --first-only -t 4 .phan/baseline.php > /tmp/baseline.php && mv /tmp/baseline.php .phan/baseline.php
```

<div class="note">

The `unexpand` post-processing step is required because Phan hardcodes
4-space indentation in `BaselineSavingPlugin.php` — this cannot be
configured via CLI or `config.php`. MediaWiki PHPCS enforces tabs, so
committing the unmodified baseline will cause PHPCS failures. On macOS
where `unexpand --first-only` is unavailable, use `sed` instead:  
`sed -i 's/ /\t/g' .phan/baseline.php`

</div>

**Configuration**

`.phan/config.php` inherits from `mediawiki-phan-config`:

``` php
$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$cfg['baseline_path'] = __DIR__ . '/baseline.php';

$cfg['directory_list'] = array_merge(
    $cfg['directory_list'],
    ['src', 'includes', 'specials']
);

$cfg['exclude_analysis_directory_list'] = array_merge(
    $cfg['exclude_analysis_directory_list'],
    ['vendor/']
);

return $cfg;
```

**Baseline**

- `.phan/baseline.php` is auto-generated — do not edit it manually

- New code must not introduce Phan issues beyond the current baseline

- When deliberately deferring a pre-existing issue, update the baseline
  via the dedicated target:  
  `make composer-phan-update-baseline`  
  This re-generates `.phan/baseline.php` and converts Phan’s hardcoded
  4-space indentation to tabs (required by MediaWiki PHPCS). Never run
  `--save-baseline` directly without this post-processing step.

- When suppressing with `@suppress`, always add an explanatory comment

**Ratcheting a suppressed issue type back on**

`suppress_issue_types` in `.phan/config.php` can list an entire issue
class as suppressed project-wide (e.g. `SecurityCheck-SQLInjection`,
`SecurityCheck-XSS`) rather than baselined per-line — typically because
it was unaffordable to fix every finding when the check was first
introduced. Treat each suppressed entry as a backlog item, not a
permanent exemption. To re-enable one:

1.  Remove the single issue type from `suppress_issue_types` locally and
    run Phan to get the full findings list for that type only.

2.  Fix each finding using the platform-provided safe primitive (e.g. an
    identifier-quoting helper on the DB abstraction, not manual string
    quoting) rather than a per-site `@suppress`.

3.  Confirm behavior is unchanged by running the affected integration
    tests against a real backend, not just the unit suite — taint-plugin
    findings cluster around DDL/dynamic-SQL and cache paths that unit
    tests often mock around.

4.  Commit the config change (removing the suppression) together with
    the fixes, so CI enforces the check on this issue type from that
    commit forward — a future regression fails fast in review instead of
    shipping.
