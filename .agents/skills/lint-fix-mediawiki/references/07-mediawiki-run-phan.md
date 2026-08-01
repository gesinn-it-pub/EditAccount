**Execution — Run Phan · MediaWiki**

Run Phan against the codebase:

``` console
make composer-phan
```

**Fixing issues**

- Fix genuine type errors, undeclared-method, and undeclared-class
  issues in new code

- For issues in legacy code not touched by the current change, update
  the baseline instead of adding `@suppress`:

  ``` console
  make composer-phan-update-baseline
  ```

  This target re-generates the baseline and post-processes it with
  `unexpand` to convert Phan’s hardcoded 4-space indentation to tabs.
  Never run `--save-baseline` directly — the unprocessed output fails
  MediaWiki PHPCS.

- When `@suppress` is unavoidable, add an explanatory comment directly
  above it

**Baseline updates**

`.phan/baseline.php` is auto-generated. After updating it, commit it
together with the code change that necessitated the update.
