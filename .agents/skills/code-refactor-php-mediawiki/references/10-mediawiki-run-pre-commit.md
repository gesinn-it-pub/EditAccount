**Execution — Pre-Commit Gate · MediaWiki**

**Do not commit until the pre-commit gate exits with code 0. If it
fails, fix all violations before proceeding.**

Run the pre-commit gate before every commit:

``` console
make ci
```

For interactive use (volume-mounted extension, no container rebuild),
use the faster pre-commit gate:

``` console
make dev-test
```

dev-test runs: lint → PHPCS → Phan → PHPUnit — without destroying Docker
volumes. Reserve `make ci` for the full pipeline verification.
