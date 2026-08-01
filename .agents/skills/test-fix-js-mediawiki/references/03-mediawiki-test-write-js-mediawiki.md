**Procedure — test:write · MediaWiki JS**

Before making any code changes to fix a bug or implement a feature:

1.  Check whether an existing test already covers the described
    behavior.

2.  If not, write or adapt a test that reproduces the issue — it must
    fail first.

3.  Only after a failing test exists, make the code changes.

4.  Re-run the test to confirm it passes (green).

**Node-based QUnit, not the in-browser runner**

Extensions in this org standardize on node-based QUnit
(`tests/node-qunit/` + jsdom + sinon), not MediaWiki’s in-browser
`Special:JavaScriptTest/qunit` runner. It runs in CI without a full
MediaWiki+browser stack, and existing `tests/qunit/` test suites are
ported over rather than kept in parallel — see the **Execution — Run
Tests (QUnit) · MediaWiki** reference file for how to run and filter
tests.

Only fall back to the in-browser runner (kept as documented legacy-only
files, e.g. see SemanticResultFormats' `tests/qunit/README.md`) for what
jsdom genuinely can’t fake: real Leaflet/canvas rendering,
`window.matchMedia`, or similarly heavy browser-only APIs. Document
**why** per file when this happens.

**Asserting "no timer was scheduled" — instrument the source, not just
the outcome**

A common bug pattern: a test waits a fixed delay (`setTimeout(fn, 0)`)
and then asserts a side effect did **not** happen (e.g.
`reloadCalled === false`). This still passes even when the code under
test **did** schedule a future timer (e.g. a 3000ms retry) — the
assertion runs before that timer would ever fire, so a regression that
fails to skip the retry goes undetected.

Instead, stub the scheduling call itself and assert on whether it was
invoked with a delay, not on whether its effect has manifested yet:

``` javascript
var originalSetTimeout = window.setTimeout;
var scheduledDelay = null;
window.setTimeout = function ( callback, delay ) {
    if ( delay !== undefined ) {
        scheduledDelay = delay;
    }
    // Still invoke the real timer -- see the pitfall below.
    return originalSetTimeout( callback, delay );
};

codeUnderTest();

originalSetTimeout( function () {
    assert.strictEqual( scheduledDelay, null, 'no timer was scheduled' );
    window.setTimeout = originalSetTimeout;
    done();
}, 10 );
```

**Pitfall: jQuery uses `window.setTimeout` internally too**

A stub that swallows every `setTimeout` call (never invoking `callback`)
doesn’t just intercept the code under test — it also blocks jQuery’s own
internal scheduling inside `$.Deferred`/`$.ajax` (called with no `delay`
argument), silently breaking unrelated assertions later in the same test
(a resolved promise’s `.done()` callback never runs). Always call
through to the real timer inside the stub, and gate the
assertion-relevant capture on `delay !== undefined` so jQuery’s
parameterless internal calls are excluded.

**Pitfall: hardcoded future dates in fixtures go stale and start
failing**

A test fixture using a literal date string (`'2026-07-30T…​'`) to
represent "today" or "a day soon" passes only until that date arrives,
then fails in CI for a reason unrelated to any regression — and if the
date is far enough out, it fails long after everyone who wrote it has
forgotten why. This is especially easy to hit when the code under test
derives its rendered range from the wall clock (`start=current`,
`defaultview`, "next 7 days").

Never hardcode a date that must remain in the future. Instead:

``` javascript
// Bad: silently starts failing once 2026-07-31 arrives.
const data = { events: [ { start: '2026-07-30T10:00:00', ... } ] };

// Good: pin the clock the code under test reads, then derive fixture
// dates relative to it.
const clock = sinon.useFakeTimers( new Date( '2026-07-30T09:00:00' ).getTime() );
// ... build fixture dates from `new Date()` here, or from the same literal
// used to seed the fake clock ...
clock.restore(); // in afterEach, or rely on the suite's global sinon.restore()
```

If the code under test cannot have its clock faked (e.g. it reads a
pre-computed date baked into fixture data rather than calling
`Date.now()` itself), derive the fixture date from `new Date()` at
test-run time instead of a literal, so the test always exercises "today"
rather than a specific calendar date that will eventually be in the past
or, worse, still render but exercise a different branch (a different
day-of-week, a different month boundary) than the one intended.

**Running tests**

See the **Execution — Run Tests (QUnit) · MediaWiki** reference file
loaded by this skill.
