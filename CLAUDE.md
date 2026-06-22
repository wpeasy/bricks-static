# CLAUDE.md 

## Project Properties
- **Plugin Name:** Bricks Static
- **Description:** Generate and serve static HTML versions of Bricks-built pages for performance.
- **Minimum WordPress:** 6.5 (required for `wp_enqueue_script_module()` ESM support)
- **Minimum PHP:** 8.0
- **PHP Namespace:** `WPEasy\BricksStatic` (vendor-prefixed to avoid colliding with the Bricks theme's own `\Bricks\` namespace + autoloader, and to sidestep `Static` being a reserved PHP keyword)
- **Constants Prefix:** `BS_`
- **Textdomain:** `bricks-static`
- **REST API Namespace:** `bs/v1`
- **Database Table Prefix:** `bs_`
- **JS Global:** `BS` (window.BS)
- **CSS Prefix:** `.bs-`

## Free/Pro Split — Two Plugins, One Codebase

This repo ships **two** plugins from one codebase:
- **Free** (`bricks-static.php`, `WPEasy\BricksStatic`, `BS_*`) — wp.org. Changelog: `CHANGELOG.md`.
- **Pro** (`pro/bricks-static-pro.php`, `WPEasy\BricksStaticPro`, `BSP_*`, own PSR-4 autoloader over `pro/src/`) — an **add-on** that `Requires Plugins: bricks-static`. Changelog: `pro/CHANGELOG.md`.

Rules:
- **wp.org compliance:** the Free zip must contain **zero functional Pro code**. Pro PHP lives only under `pro/`; the build allowlist (`scripts/make-zips.mjs`, `npm run zip`) stages Free from an explicit allowlist and **fails if any staged Free PHP references `BricksStaticPro`**. Pro Svelte panels live in `pro/src-svelte/pro/` and are never imported by the Free bundle (only inert teasers ship in Free).
- **One toggle point:** `Support\Edition` resolves the edition/capabilities; everything (PHP + JS) reads from it. Pro flips it via the `bs_license_edition` filter (its `Licensing\LicenseEnforcer`). Never gate a feature by anything other than `Edition`/capabilities.
- **Seam, not fork:** Pro plugs into Free via hooks — `bs_loaded`, `bs_register_rest_routes`, the `Sync\Pipeline` replacer/extra-file registry, and the `window.BS` JS panel registry. Add new shared features as a Free seam Pro registers into; never have Free reference a Pro class.
- **Edition gating is three-layer** (UI/runtime/storage), e.g. the destination cap: `Destinations::can_add()`/`visible_objects()` (storage), `DestinationsController` 403 (REST), disabled "+ Add" (UI). Downgrades **hide, never delete** (`visible_objects()`); use it (not `objects()`) anywhere destinations are shown or synced.
- **Versioning/changelogs:** Free and Pro version independently; `BSP_MIN_FREE` is the compatibility floor (bump only when Pro needs a new Free seam). `src-svelte/shared/` + `src-svelte/lib/` compile into **both** bundles, so a change there bumps both. See `.claude/commands/commit-version.md` (project-local, split-aware).
- **Dev setup:** a directory **junction** `wp-content/plugins/bricks-static-pro` → `bricks-static/pro` makes WP see both plugins from the one repo (the junction lives outside the repo; git ignores it).

## Required Reading

**IMPORTANT:** You MUST read the following documentation files BEFORE writing any code for this project:

| File | Purpose |
|------|---------|
| **CODE_STANDARDS.md** | Naming conventions, security, PHP/JS/CSS standards |
| **SECURITY_PATTERNS.md** | Non-negotiable security rules (admin-only REST model, `Support\UrlSafety` SSRF guard, TLS/`sslverify` rule, `UnzipScript` hardening, SFTP host-key, `{@html}`/`wp_kses_post`). Read before touching any REST controller, the deploy/transport layer, URL fetching, or stored-HTML rendering. |
| **WORDPRESS.md** | Plugin header template and WordPress configuration |
| **SVELTE5_IMPLEMENTATION.md** | Svelte 5 runes and patterns (avoid Svelte 4 syntax) |
| **assets/css/bs-framework.css** | Base framework: design tokens (fluid spacing/type, borders, admin colors) and base styles. Scope admin UI in a `.bs` container and reference `--bs-*` tokens. |

> These files are **references**, not includes — read the relevant one before working in its area. Do not inline or duplicate their content into this file.

---

## Preview Iframe — Do NOT Inject Styles or Run Feature Code

**CRITICAL:** The Bricks Builder preview iframe renders the user's frontend content. Plugin code must NEVER inject styles, CSS variables, or run feature code inside this iframe — doing so breaks user content (e.g. form styling, color schemes).

- The base framework CSS (`bs-framework.css`, which sets `color-scheme` + `light-dark()`) breaks native form controls — never load it in the iframe.
- Never set `color-scheme` on the iframe body or inject `:root` variables meant for our UI.

**PHP guard pattern:**
```php
$is_main = function_exists('bricks_is_builder_main') && bricks_is_builder_main();
$is_iframe = function_exists('bricks_is_builder_iframe') && bricks_is_builder_iframe();
if ($is_main || (!$is_main && !$is_iframe)) {
    // Main-window-only assets here
}
```

Code that intentionally targets the iframe document (e.g. content overlays) must use `iframeDoc` / `contentDocument` explicitly, scope all injected CSS with `.bs-` prefixed classes, and never set `color-scheme` or inject `:root` variables.

---

## Development Workflow

See **CODE_STANDARDS.md** for naming/security/style rules and **SVELTE5_IMPLEMENTATION.md** for Svelte 5 runes. The points below are build-system lessons that aren't obvious from those files.

### After Writing Code

1. **Run the type check after significant changes:** `npm run check` (after adding files, modifying interfaces, or structural changes).
2. **Verify before removing code:** search the whole file (and `src-svelte/`) for usages before deleting any function, variable, or import.
3. **Update types immediately:** when using a new property on an interface, add it to `src-svelte/shared/types.ts` right away.

### DOM Collections — NEVER Use `for...of`

**CRITICAL:** Never use `for...of` on DOM host-object collections in Vite-compiled code — use index-based `for` loops. Affected: `StyleSheetList`, `CSSRuleList`, `NodeList`, `HTMLCollection`, `DOMTokenList`, `NamedNodeMap`.

```typescript
// ✗ WRONG — for...of on DOM collections silently fails on some hosting environments
for (const sheet of document.styleSheets) { ... }

// ✓ CORRECT — index-based iteration works everywhere
const sheets = document.styleSheets;
for (let s = 0; s < sheets.length; s++) { ... }
```

**Why:** DOM collections are host objects whose `Symbol.iterator` comes from the browser, not the JS engine. Vite/esbuild's `for...of` iterator helpers interact differently with the CSSOM in production HTTPS environments. Index loops bypass the iterator protocol entirely.

When checking DOM-object types across window boundaries (e.g. a detached panel reading the main window's stylesheets), **duck-type, don't `instanceof`** — constructors differ across windows:

```typescript
// ✓ CORRECT — duck-type by checking for the .style property
const style = (rule as CSSStyleRule).style;
if (style) { ... }
```

### Icons — Use a Centralized Icon System

Never inline raw SVGs. Keep all icons in one module (`src-svelte/lib/icons/`) — Svelte components import named `Icon*` components, TS files import `ICON_*` HTML strings for `innerHTML`. Add new icons there, don't paste SVG markup into components.

### Build Commands

| Command | Purpose |
|---------|---------|
| `npm run check` | TypeScript/Svelte type check (run often!) |
| `npm run build` | Production build |
| `npm run dev`   | Development build with watch |

### `shared.js` — IIFE Wrapper & No ES Imports

If you ship a `shared.js` loaded as a **non-module** script via `wp_enqueue_script()`:

1. **No ES imports** and **no dynamic `import()`** in its entry — Vite emits top-level `import`/`__vitePreload` that breaks in non-module context. Import statically so Vite inlines, or expose work via a global from a real ES-module bundle.
2. **IIFE-wrap the output** (`vite.config.ts`) so minified variable names (like `_`) don't leak to global scope and clobber WordPress globals (underscore.js).

### `wp_localize_script` Booleans — Use Truthy Checks, Not `=== true`

Top-level keys passed through `wp_localize_script` arrive in JS as the **string** `'1'` / `''`, not `true` / `false`. `if (data.isMainWindow === true)` is therefore **always false at runtime**.

```ts
// ✗ WRONG — strict equality against '1'/'' is always false
if (data.isMainWindow === true) { ... }
// ✓ CORRECT — truthy/falsy accepts both true and '1'
if (data.isMainWindow) { ... }
```

### Dynamic Imports — Use `import()` for Optional Code

Use dynamic `import()` for debug tools, feature modules that can be disabled, large context-specific dependencies, and any code not hit on every load. Only skip it for always-needed core, tiny (<1 KB) modules, or `shared.js` (see above).

```typescript
let mod: typeof import('./debug/overflow-detection') | null = null;
async function start() { mod ??= await import('./debug/overflow-detection'); mod.start(); }
```

### CSS Chunk Loading

When Vite splits scoped Svelte CSS into shared chunks, browsers do **not** auto-load a chunk's CSS when its JS imports. Walk the Vite manifest and enqueue every transitive `css` entry (a single `enqueue_entry_css()`-style helper per entry point), strip the hash so handles stay stable across builds. **Symptom of a missing chunk:** components render with correct scoped class names but appear completely unstyled — often "works locally" due to stale cached CSS, only fresh installs reveal it.

---

## Deviation Reporting

When deviating from established patterns (vanilla JS instead of Svelte, new pattern instead of an existing one, anything against this file for a technical reason), tell the user **before** implementing, using:

> **Note:** [What I'm deviating from] — [Technical reason] — [Alternative if you prefer]

---

## Code Organization — DRY

Centralize and reuse — this is a blocking requirement:

- **Defaults / constants / config** live in one place (`src-svelte/shared/defaults.ts`, `shared/constants.ts`) and are imported, never duplicated.
- **State** lives in shared stores, not duplicated per component.
- **Components** live in `src-svelte/lib/` — check there before creating a new one.
- Before writing new code, search for an existing implementation; if you find duplication, refactor to centralize first.

---

## Tooltips — Use Popovers, Not `title`

Never use the `title` attribute. Use `data-balloon` popovers for consistent styling and positioning:

```typescript
button.setAttribute('data-balloon', 'Show Panel (Ctrl+Shift+E)');
button.setAttribute('data-balloon-pos', 'down-left'); // up | down | left | right | combos
```

Include keyboard shortcuts in the tooltip text when available.

---

## CSS Class Names — Avoid Bricks Toolbar Collisions

Any plugin UI mounted inside `#bricks-toolbar` MUST use **prefixed state class names** — Bricks ships ID-anchored rules (e.g. `#bricks-toolbar .active { ... }`, specificity 1,1,0) that beat most class chains.

```ts
// ✗ WRONG — overridden by `#bricks-toolbar .active`
<button class="bs-pill" class:active={isSelected} />
// ✓ CORRECT — `.is-active` is specific enough
<button class="bs-pill" class:is-active={isSelected} />
```

`!important` is a worse fix — it propagates and starts a specificity arms race. Renaming the state class (`is-`) is permanent and free. The same risk applies to `.primary`, `.danger`, `.selected`, `.open`, `.disabled`, etc.

---

## Bricks UI Navigation — Mutate Vue State, Never Match `data-balloon`

**Read live Bricks data from Vue `$_state`, not `bricksData.loadData`.** `bricksData.loadData.*` is the load-time snapshot and does NOT update when the user edits colors/variables/classes mid-session. Read `.brx-body.__vue_app__…$_state.<thing>` for current editor state.

**Any code that programmatically navigates the Bricks UI MUST go through Vue `$_state`, NOT DOM selectors matching `data-balloon`** — `data-balloon` is the localized tooltip, so `[data-balloon="Variables"]` returns null on every non-English install.

```ts
function getBricksVueProps() {
    const brxBody = document.querySelector('.brx-body');
    return brxBody?.__vue_app__?.config?.globalProperties ?? null;
}
// ✓ Vue-state mutation, i18n-safe
const props = getBricksVueProps();
if (props?.$_state) props.$_state.popupTab = 'variables';
```

Class names on Bricks UI containers (`.bricks-popup.styles.variables`) ARE i18n-safe (derived from the internal key) — safe to **read**, never **write** (Vue owns rendering). `placeholder` attributes are also localized — select inputs by DOM position, not placeholder text.

---

## Iframe-Aware Click-Outside — `mousedown` + Capture

"Click anywhere to dismiss" handlers must listen on **both** the main `document` AND the Bricks preview iframe's `contentDocument` — iframe clicks fire on the iframe's document and don't bubble to the parent.

- Use **`mousedown`** (not `click`) in **capture** phase — Bricks' Vue handles `click` and may `stopPropagation()`.
- **Cache the iframe document instance** you attached to, and detach from that same instance — `contentDocument` can change (navigation/hot reload), and re-querying on teardown leaks the original listener.

---

## Bricks Permissions Integration

Features that modify Bricks data must be gated by Bricks' `Builder_Permissions` system (admins get `full_access`).

- **PHP:** `\Bricks\Builder_Permissions::user_has_permission()`.
- **JS:** a `shared/permissions.ts` feature→permission map with `hasPermission()` / `hasFeaturePermission()` helpers.

Enforce at three layers: UI (disable the control with a reason), runtime (check before activating), and global settings (read-only mode for non-admins).

---

## REST Liveness vs Diagnostics — Two Endpoints, Two Permission Models

If you add health/diagnostics routes, keep them on separate permission models:

| Route | Auth | Purpose |
|---|---|---|
| `GET /bs/v1/health` | `__return_true` (no auth) | Boot-time liveness probe. Returns `{ ok, version, ts }`. **Must stay no-auth** — a 401 here is indistinguishable from REST being broken. |
| `GET /bs/v1/health/diagnostics` | `manage_options` | Full server/WP snapshot for an admin Health tab. Never leak config to lower-privilege users. |

Add new diagnostic data to `/health/diagnostics` (admin-only), never `/health`. Omit email/PII so the payload is safe to paste into public support channels.

---

## Per-Destination Replacement Subsystems

Replacements (Text, Media, Links, Videos) all follow ONE pattern — copy it for any new one:

1. **Collector** (`src/Media/*Collector.php`) scans the last render's cached HTML (`Manifest::RENDER_OPTION`) and returns deduped items with the page(s) they appear on, for the dashboard list.
2. **Replacer** (`src/Render/*Replacer.php`) rewrites the matched thing in the per-destination deploy copy. Two non-negotiable rules, learned from real bugs:
   - **Tag-scoped, never global** — match the specific element/attribute (or, for text, only the runs between tags). A blanket `str_replace`/regex across the whole document corrupts attributes, scripts, JSON-LD, or body text. `TextReplacer` splits out `<script>/<style>/<!-- -->` and operates on the right side only.
   - **Entity-decode before matching** — attribute/`url()` values carry `&amp;`, `&quot;`, percent-encoding, etc. Decode the captured value (`html_entity_decode(..., ENT_QUOTES | ENT_HTML5)`) before comparing to the stored key; `esc_attr()` the replacement on write.
3. **Storage** on `Destination` (`*_replacements()` + a `sanitize_*` ); included in `Destination::for_display()` and `apply()`.
4. **Deploy**: applied in `Runner::build_deploy_manifest()` (only writes a per-destination page copy when the page actually changed) and folded into `Runner::sync_signature()` so a replacement change flips "in sync".
5. **REST** `GET /bs/v1/<thing>` (admin-only) + a Svelte panel in the Replacements **accordion** (one section open at a time), auto-saving and pruning stale entries.

Media/Videos that swap to a library attachment must also add the new file (and image variants) to the deploy manifest so it uploads. The Videos replacer additionally rewrites an embed's source `origin=` (incl. percent-encoded) to the destination.

---

## Internationalization (i18n) — PHP dictionary, NOT `@wordpress/i18n`

UI strings are localized with a **PHP-dictionary** pattern, deliberately not `@wordpress/i18n` + `wp_set_script_translations()` (whose md5-of-source-path `.json` matching is brittle with Vite's hashed/code-split bundles — translations silently vanish). This mirrors the sibling `ab-bricks-productivity` plugin.

- **Strings live in PHP:** `src/Support/I18n.php` (`I18n::all()`, domain `bricks-static`) and `pro/src/Support/I18n.php` (domain `bricks-static-pro`), each `'jsKey' => __('English', 'domain')`. One `.mo` translates both PHP and JS.
- **Bridge:** `Admin\Menu` localizes `I18n::all()` → `window.bsData.i18n`; `Admin\ProMenu` → `window.bspData.i18n`. The Svelte helper `src-svelte/shared/i18n.ts` exposes `__()` / `__f()` (sprintf `%s`/`%d` + positional `%1$s`) reading the merged dict; components call `__('jsKey')`. Strings with inline markup use `{@html __(...)}` (trusted dictionary only).
- **Loading:** `load_plugin_textdomain` on `init` in both plugins; `.mo` in `languages/` (Free) and `pro/languages/` (Pro). Every JS-consuming bundle is `type="module"`, so the helper's ES `import` is fine (no IIFE concern — that rule is only for a non-module `shared.js`, which this plugin doesn't ship).
- **Regenerate:** `wp i18n make-pot` → `python scripts/i18n_build.py extract` → translate into `scripts/i18n/<locale>.json` → `python scripts/i18n_build.py assemble` (writes `.po` + `.mo`). Shipped: fr_FR, de_DE, it_IT, es_ES, nl_NL. Scope so far: dashboard + Pro panels (Docs prose left English).

## Description

The **Bricks Static** plugin generates and serves static HTML versions of pages built with the Bricks builder. By rendering Bricks pages to static HTML and serving them directly, it reduces server load and improves front-end performance.


