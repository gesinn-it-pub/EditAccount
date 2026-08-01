**Procedure — code:fix**

1.  Reproduce the bug with a failing test first. This test is the proof
    the bug exists.

2.  Run that test against the unmodified code and observe it fail, for
    the reason the bug describes. A test that was only written, never
    executed red, is not proof of anything — it may pass by accident
    (wrong mock, wrong assertion, code path never reached) and give
    false confidence in the fix that follows.

3.  Fix the code until the test passes.

4.  Never fix code without a reproducing test — you cannot verify the
    fix is correct.

5.  Before considering the fix complete, re-read the test and ask: does
    every mock/stub in it actually exercise the real code path where the
    bug lives, or does it substitute away the exact behavior under test?
    A mock that returns a fixed value regardless of its input can
    silently skip the line that matters.

6.  If the fix makes a previously dead/unreachable branch reachable (a
    stale selector, a guard that was always false, a condition that
    never matched): there is no prior working behavior to fall back on,
    only the one path your new test exercises. Enumerate every other
    caller/context that will now run through this branch and check each
    one, in the real runtime if the unit-test harness can substitute
    away environment-specific behavior (see the platform-specific
    test-write procedure for what "real runtime" means there) — a fix
    that is correct in isolation can still corrupt output in a sibling
    context the test never looked at.

7.  Before writing the commit message, release notes, or a code comment
    describing the bug’s prior impact ("X was never rendered", "Y always
    failed"): verify that claim against the actual unmodified behavior
    (run it, don’t infer it from reading the code) — an overstated or
    understated diagnosis is itself a defect that will mislead the next
    reader.

8.  If the fix addresses a reported issue: after pushing, close the
    issue in the issue tracker with a comment referencing the commit.

9.  Write the commit message as symptom → root cause → fix rationale,
    and end it with an explicit scope boundary: name what you
    deliberately left unchanged and why it is safe to leave alone (e.g.
    "the same pattern exists in the read-only variant of this call,
    which is unaffected because it never receives user input"). A
    reviewer who cannot tell what you checked and ruled out has to redo
    that analysis themselves.

10. Keep the commit to one mechanism. If the same bug has several
    independent triggering call sites, fix all of them in this commit;
    if you also notice an unrelated improvement while you’re in the
    file, make it a separate commit instead of riding along — a bundled
    drive-by change forces the reviewer to re-derive which part of the
    diff is the actual fix.

11. When responding to an automated reviewer’s finding (a bot or another
    agent), investigate before you accept or dismiss it — reproduce what
    it claims, don’t trust the finding or your own first instinct. Three
    outcomes, each stated explicitly in the follow-up commit or reply:
    fix it (name the confirmed mechanism, not just "addressed
    feedback"); it is a real but non-blocking risk (fix it if cheap,
    otherwise leave a comment at the site documenting the risk so the
    next person does not have to rediscover it); or it does not apply
    here (state the specific reason it doesn’t, e.g. the flagged input
    can’t actually reach this path).
