**Execution — Browser Debugging with Playwright · MediaWiki**

Use Playwright inside the running wiki container to debug client-side
behaviour that is not visible in PHP logs or HTML source: ResourceLoader
module states, DOM state after JS execution, JS runtime errors, and user
interaction (clicks, form submission).

Use this approach when:

- Verifying that a ResourceLoader module is registered and loaded
  (`ready`) after a server-side change

- Checking whether a JS-rendered widget (tabs, dropdowns, dialogs)
  actually appears in the DOM

- Catching uncaught JS exceptions (`pageerror`) or console warnings
  during page load

- Simulating user interaction (clicking a tab, submitting a form) and
  inspecting the resulting JS state

For HTTP response analysis or HTML source inspection, `curl` from inside
the container is sufficient and faster.

**Mandatory before declaring a fix or new feature done, not just
optional debugging**

A green node-qunit/jsdom suite proves the code under test behaves as
asserted — it does not prove the feature renders correctly on a live
wiki. jsdom has no layout engine (every element reports zero
width/height/position) and no stylesheet cascade, so it cannot catch:

- A DOM-insertion callback (`eventRender`, a widget’s `_create()`, an
  `afterEach` hook) that receives a **shared** element spanning more
  than one logical item under real layout conditions, even though a
  synthetic unit-test fixture handed it exactly one item — this is
  precisely how a selector fix that is correct in isolation ends up
  writing the wrong item’s content into every sibling once the fix makes
  a previously-dead branch live.

- Missing or wrong CSS for anything newly inserted into the DOM (no rule
  at all reads as "no visible defect" in jsdom, but overflows,
  truncates, or clips content in an actual browser).

- Any layout-, timing-, or paint-dependent behavior (stacking/overlap
  algorithms, scroll position, transitions).

Before marking a JS fix or feature complete, run it once through this
Playwright setup against a real wiki page exercising the changed code
path, and visually/structurally confirm the actual rendered DOM and
screenshot match intent — do not rely on unit-test green alone, and do
not assert in a commit message, code comment, or release note that a
real-browser reproduction "would be needed" without having tried this
first.

**Get the container name:**

``` console
docker ps --format '{{.Names}}'
```

**One-time setup** (idempotent, safe to repeat):

``` console
docker exec <container> bash -c "apt-get update && apt-get install -y nodejs npm chromium"
docker exec <container> bash -c "cd /tmp && npm install playwright"
```

**Script template** — create `/tmp/pw.js` inside the container:

``` console
docker exec <container> bash -c "cat > /tmp/pw.js" <<'EOF'
const { chromium } = require('/tmp/node_modules/playwright');
(async () => {
    const browser = await chromium.launch({
        executablePath: '/usr/bin/chromium',
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });
    const page = await browser.newPage();
    page.on('pageerror', err => console.log('PAGEERROR:', err.message));
    page.on('console', msg => console.log('CONSOLE:', msg.type(), msg.text()));

    await page.goto('http://127.0.0.1/index.php/...', { waitUntil: 'networkidle', timeout: 30000 });

    // Read JS state
    const state = await page.evaluate(() =>
        mw.loader.moduleRegistry['ext.mymodule']?.state
    );
    console.log('module state:', state);

    // Simulate interaction
    await page.click('.some-selector');

    await browser.close();
})().catch(e => { console.error(e.message); process.exit(1); });
EOF
```

**Run the script:**

``` console
docker exec <container> node /tmp/pw.js
```

<div class="note">

Use `http://127.0.0.1` (not the public hostname) — no authentication is
required when accessing the wiki from within the container.

</div>

<div class="note">

`/tmp/pw.js` does not survive a container restart. Re-create it from the
template above if needed.

</div>
