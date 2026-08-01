**Procedure — code:refactor**

1.  Run the full test suite first. All tests must be green before you
    start.

2.  Check test coverage for the files you intend to change. If coverage
    is below ~80% on the affected code paths, warn explicitly before
    proceeding: low coverage means the refactoring cannot be verified
    safely. Do not block, but make the risk visible.

3.  Make structural changes (extract method, rename, move class, etc.).

4.  Run the full test suite again. All tests must still be green.

5.  If a test breaks, you changed behavior — revert the change or
    explicitly justify updating the test.

6.  Never change test logic during a refactor unless the test itself was
    wrong.

**Replacing a dependency or legacy internal wrapper — sequencing for a
large migration**

When retiring a third-party library or an internal wrapper class in
favor of a platform-native equivalent, never do it as one large commit.
Sequence it as independently-shippable steps, each keeping the suite
green:

1.  Add the native-backed replacement first, as an additive,
    self-contained unit with its own tests — no call site changed yet.
    State explicitly in the commit message that it is additive and
    nothing is rewired.

2.  Migrate call sites in small commits grouped by consuming subsystem,
    not by file count. Each commit states what is behavior-preserving
    (e.g. "type hints only", "cache key format unchanged, entries
    round-trip") so a reviewer knows exactly what to double-check.

3.  Convert each call site’s tests in the same commit as the call site —
    never leave a commit where production code has moved on but its test
    still exercises the old dependency.

4.  Only after grepping for zero remaining references, remove the old
    wrapper/registration and drop the dependency from the manifest, and
    name in that commit’s message what made it safe ("X was the last
    consumer").

This ordering keeps every intermediate commit bisectable: a regression
localizes to one small conversion step instead of a sweeping rewrite.

**Migrating from a service locator to constructor injection — sequencing
for a large migration**

When converting a codebase (or a large subsystem of one) from resolving
collaborators through a global locator/registry inside methods to
injecting them through the constructor, sequence by **resolution
mechanism**, not by class:

1.  Group consumers by how they are instantiated (e.g. all queue/job
    classes, then all controller/handler classes, then all classes built
    through the same factory-spec mechanism) — each mechanism has one
    shared wiring point that, once proven correct for the first class,
    mechanically repeats for the rest.

2.  Within a group, convert one class per commit: inject what already
    has a stable global registration; leave the locator call in place
    for anything that doesn’t yet, with an inline comment naming why
    ("not yet a registered global service") rather than forcing a fake
    injection.

3.  Route internal re-construction call sites (a class that rebuilds or
    re-queues itself, recursive construction) through the same injected
    factory used by external callers — a locator bypass left on an
    internal-only path is the most common miss, since it doesn’t show up
    until something depends on the override taking effect there too.

4.  Tag every commit in the migration with a shared tracking-issue
    reference so the whole initiative is greppable as one unit.

5.  Close the initiative with one final "audit" commit whose message
    says so explicitly, and whose body enumerates and converts whatever
    the mechanical per-class passes missed — this is the searchable
    signal that the migration is complete.

**Eager vs. lazy resolution for an injected collaborator**

Default to resolving an injected collaborator eagerly (a plain
constructor parameter). Fall back to a lazily-invoked provider (a
closure or factory-typed property, invoked only when first needed) only
when eager resolution would trigger a side effect prematurely — e.g.
constructing the collaborator has the side effect of building and
caching a singleton elsewhere in the system, before every other
component that needs to configure that singleton has had a chance to
run. State that reason in an inline comment at the property/parameter —
an uncommented lazy provider inside otherwise-eager code reads as an
oversight, not a deliberate choice, and invites a future "cleanup" that
reintroduces the bug.
