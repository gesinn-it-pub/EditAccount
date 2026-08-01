**Procedure — test:write · MediaWiki**

Before making any code changes to fix a bug or implement a feature:

1.  Check whether an existing test already covers the described
    behavior.

2.  If not, write or adapt a test that reproduces the issue — it must
    fail first.

3.  Only after a failing test exists, make the code changes.

4.  Re-run the test to confirm it passes (green).

**MediaWiki test base classes**

Use the appropriate base class:

- `MediaWikiUnitTestCase` — pure unit tests (no database, no service
  container); fastest

- `MediaWikiIntegrationTestCase` — integration tests that need the
  service container or database

- `MediaWikiLangTestCase` — when language handling is under test

- `SpecialPageTestBase` — extends `MediaWikiIntegrationTestCase`; use
  when testing Special Pages

- `HookRunnerTestBase` — unit test base for `HookRunner` classes;
  validates hook delegation automatically

Do **not** extend `MediaWikiIntegrationTestCase` by default — use
`MediaWikiUnitTestCase` unless integration with MW services is required.

**Testing Special Pages with SpecialPageTestBase**

Extend `SpecialPageTestBase` and implement `newSpecialPage()` to return
the page instance:

``` php
class SpecialFooTest extends SpecialPageTestBase {
    protected function newSpecialPage() {
        return MediaWikiServices::getInstance()
            ->getSpecialPageFactory()->getPage( 'Foo' );
    }
}
```

Key methods available in tests:

- `$this→executeSpecialPage( $subpage, new FauxRequest( $query, $isPosted ) )`
  — renders the special page and returns `[ $html, $context ]`

- `$this→setUserLang( 'qqx' )` — use `qqx` locale so message keys appear
  literally in output, making assertions locale-independent

- `$this→setGroupPermissions( '*', 'edit', false )` — adjust
  permissions; combine with
  `$this→expectException( PermissionsError::class )` to test access
  control

- `$this→insertPage( $title, $content )` — create a page as fixture

- `$this→getServiceContainer()` — access MW services

Annotate the class with `@group Database` when the test writes to the
database.

If the special page requires a permission (e.g. `upload`), pass a
matching performer as the fourth argument — otherwise
`executeSpecialPage()` runs as an anonymous user and throws
`PermissionsError`:

``` php
$performer = $this->getTestUser( [ 'sysop' ] )->getAuthority();
[ $html, ] = $this->executeSpecialPage( '', new FauxRequest( [] ), null, $performer );
```

If the page checks a feature flag via config (e.g.
`UploadBase::isEnabled()` reads `wgEnableUploads`), set it before
calling `executeSpecialPage()`:

``` php
$this->setMwGlobals( 'wgEnableUploads', true );
```

**Asserting HTML output**

Parse the returned `$html` with `DomDocument` and `DomXPath` to assert
on form fields, links, or rendered content:

``` php
[ $html, ] = $this->executeSpecialPage( '', new FauxRequest( [] ) );
$dom = new DomDocument;
$dom->loadHTML( $html );
$xpath = new DomXpath( $dom );
$input = $xpath->query( '//input[@name="wpFoo"]' )->item( 0 );
$this->assertNotNull( $input );
```

**Testing HookRunner classes with HookRunnerTestBase**

If the extension has a `HookRunner` class (a class that implements hook
interfaces and delegates to `HookContainer::run()`), add a unit test
that extends `HookRunnerTestBase`. This test automatically verifies that
every hook method delegates correctly — right hook name, right argument
signature.

First check whether a `HookRunner` exists:

``` console
find includes -name "*HookRunner.php" | head -1
```

If one exists, the test is a one-liner:

``` php
use MediaWiki\Tests\HookContainer\HookRunnerTestBase;

/**
 * @covers \FooHookRunner
 */
class FooHookRunnerTest extends HookRunnerTestBase {
    public static function provideHookRunners() {
        yield FooHookRunner::class => [ FooHookRunner::class ];
    }
}
```

Place the test under `tests/phpunit/unit/` — no database needed.

Do **not** introduce a `HookRunner` class just to have this test. Only
add the test when the extension already uses the Hook Runner pattern.

**Testing parser functions**

Extend `MediaWikiIntegrationTestCase` and use the shared parser
singleton for simple parse-and-assert tests:

``` php
$parserOutput = $this->getServiceContainer()->getParser()->parse(
    $wikitext,
    Title::makeTitle( NS_MAIN, 'Test' ),
    ParserOptions::newFromAnon()
);
```

Use `getParserFactory()→create()` only when you need to mutate the
parser instance (e.g. registering a custom tag hook via `setHook()`).

Extract the raw HTML with `getRawText()` — do **not** use `getText()`,
which is deprecated since MW 1.42 (T353257) and has side-effects on
`ParserOutput`:

``` php
$html = $parserOutput->getRawText();
```

`Parser::parse()` wraps inline content in `<p>…</p>\n`. Strip it with
`Parser::stripOuterParagraph()` before asserting on plain text output:

``` php
$text = Parser::stripOuterParagraph( $parserOutput->getRawText() );
$this->assertSame( 'expected', $text );
```

Combine with `@dataProvider` to express wikitext → output cases as a
table:

``` php
public static function provideParserFunction(): array {
    return [
        'basic case'  => [ '{{#myfunc:a|b}}', 'expected output' ],
        'empty input' => [ '{{#myfunc:}}',    '' ],
    ];
}

#[DataProvider( 'provideParserFunction' )]
public function testMyFunc( string $wikitext, string $expected ): void {
    $out = $this->getServiceContainer()->getParser()->parse(
        $wikitext,
        Title::makeTitle( NS_MAIN, 'Test' ),
        ParserOptions::newFromAnon()
    );
    $this->assertSame(
        $expected,
        Parser::stripOuterParagraph( $out->getRawText() )
    );
}
```

Annotate the class with `@group Database` — the parser service requires
the database to be initialised even when no pages are written.

**Testing Action classes**

Action tests (subclasses of `Action`) always extend
`MediaWikiIntegrationTestCase` and carry `@group Database` — even when
no pages are written — because `Action::__construct()` requires a real
`Article` which resolves through the service container.

Construct the action under test through a private factory method, not
inline in `setUp()`:

``` php
private function newAction( Title $title, array $requestParams = [] ): MyAction {
    $context = new DerivativeContext( RequestContext::getMain() );
    $context->setTitle( $title );
    $context->setRequest( new FauxRequest( $requestParams ) );
    $article = Article::newFromTitle( $title, $context );
    return new MyAction( $article, $context );
}
```

Use `FauxRequest` for GET and POST simulation; pass params as the first
argument and set `true` as the second argument for POST:

``` php
new FauxRequest( [ 'action' => 'myaction' ] )         // GET
new FauxRequest( [ 'token' => '...', 'from' => '...' ], true )  // POST
```

When the action under test calls
`MediaWikiServices::getInstance()→getPermissionManager()` (a service
lookup, not constructor-injected), override it with
`$this→setService()`:

``` php
$permManager = $this->createMock( PermissionManager::class );
$permManager->method( 'userCan' )->with( 'edit', $user, $title )->willReturn( false );
$this->setService( 'PermissionManager', $permManager );
```

For static methods that accept an `IContextSource`, mock the context
directly rather than building a full `DerivativeContext`:

``` php
$context = $this->createMock( IContextSource::class );
$context->method( 'getTitle' )->willReturn( $title );
$context->method( 'getUser' )->willReturn( $user );
$context->method( 'getRequest' )->willReturn( new FauxRequest( $params ) );
```

Override config globals set via `global $wgFoo` with
`$this→setMwGlobals()` in `setUp()`. Reset to the default before each
test so branches under test are explicit:

``` php
protected function setUp(): void {
    parent::setUp();
    $this->setMwGlobals( 'wgMyExtensionFlag', false );
}

public function testBranchEnabled(): void {
    $this->setMwGlobals( 'wgMyExtensionFlag', true );
    // ...
}
```

Use `overrideConfigValues()` instead when the extension reads config
through the MW config system (registered in `extension.json` under
`config` and accessed via
`$this→getServiceContainer()→getMainConfig()→get()`). `setMwGlobals()`
and `overrideConfigValues()` are not interchangeable — use whichever
matches how the production code reads the value.

Assert on tab order by comparing `array_keys()`:

``` php
$this->assertSame( [ 'view', 'formedit', 'edit', 'history' ], array_keys( $links['views'] ) );
```

**Test fixtures**

- Use `setUp()` and `tearDown()` for test-scoped fixtures

- For database fixtures, use `addDBDataOnce()` (run once per class) or
  `addDBData()` (run per test)

- Use `getMockBuilder()` / `createMock()` for dependencies; prefer
  constructor injection so mocks can be passed in

**Chaining teardown steps safely**

When `tearDown()` performs more than one cleanup action (delete fixture
pages, roll back an open transaction, call `parent::tearDown()`), a
single unguarded step that throws can abort every later step — including
the one that matters most for the **next** test (transaction rollback,
parent cleanup). Nest each step in its own `try/finally` so a failure in
one never skips the rest:

``` php
protected function tearDown(): void {
    try {
        $this->pageDeleter->deleteCreatedPages();
    } finally {
        try {
            $this->rollbackOpenTransactions();
        } finally {
            parent::tearDown();
        }
    }
}
```

Never silently swallow an exception in cleanup code
(`catch ( \Exception $e ) {}`). Catch `\Throwable`, log it, and continue
— a silently swallowed cleanup failure hides the real cause of a later,
unrelated-looking test failure in a different test class.

**Removing dead mock/service-registration scaffolding**

A test-scoped service-registration helper (registering a mock on a
shared/global container for the duration of a test) becomes dead once
the production code it targeted is fully constructor-injected — the
system under test no longer reaches it through the container. Before
deleting such a registration:

1.  Delete one registration and re-run only that test class. Keep the
    deletion only if the test stays green **at the same assertion
    count** — a mock nothing calls can still report "green" for the
    wrong reason (e.g. a never-verified `expects( $this→never() )`).

2.  Re-run the full test suite, not just the one class. A registration
    inside a `finally` block, or one that restores a mutated shared
    value (config, settings) rather than injecting a collaborator, is
    load-bearing state-restoration, not dead scaffolding — deleting it
    can silently corrupt global state for every test class that runs
    afterward in the same process, often dozens of unrelated failures
    far from the file you edited.

3.  If step 2 finds a regression, keep that specific registration and
    document inline why it is still required (e.g. "restores shared
    Settings state mutated by this test").

**When a fix for a flaky/intermittent test doesn’t help**

Do not trust code-review-level plausibility as proof that a fix for a
flaky or intermittent failure (lock timeouts, race conditions,
order-dependent state) actually works. After landing it, watch the
failure rate empirically. If the failure becomes more frequent or does
not improve, revert immediately rather than layering a second theory on
top of an unproven first one — state the empirical evidence in the
revert message ("X became more frequent, not less"), not just "didn’t
work," so the next attempt has the diagnostic signal you gathered.

**Diagnosing test breakage after a platform/dependency upgrade**

When a test starts failing after upgrading the platform, framework, or a
dependency, never just loosen the assertion to make it pass again —
identify the exact mechanism that changed first, then pick the response
that fits:

- If the upgrade changed **which code path** produces a value (e.g. a
  cached path now returns before a fresh computation would run), make
  the assertion branch explicitly on the version/condition, with a
  comment naming the mechanism — this documents the behavior change
  instead of hiding it.

- If the upgrade changed **incidental output structure** but the
  property under test is unchanged (e.g. generated markup shape changes
  but the semantic identity does not), assert on the stable semantic
  anchor instead of the structure — this makes the test resilient to the
  next incidental change too, not just this one.

- If the upgrade’s stricter/different behavior trips an **irrelevant**
  code path that the test doesn’t actually exercise on purpose, disable
  that irrelevant feature at the test-environment boundary rather than
  restructuring the assertion or the test.

**Running tests**

See the **Execution — Install Dependencies · MediaWiki** and **Execution
— Run Tests (PHPUnit) · MediaWiki** reference files loaded by this
skill.
