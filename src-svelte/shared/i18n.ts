/**
 * Internationalization helper for the Svelte/JS admin UI.
 *
 * Strings are owned by PHP: the Free plugin localises its dictionary onto
 * `window.bsData.i18n` (see `Support\I18n` + `Admin\Menu`) and the Pro addon
 * localises its own onto `window.bspData.i18n` (see Pro `Support\I18n` +
 * `Admin\ProMenu`). Both are already translated server-side by the loaded `.mo`
 * for the active locale, so the JS just looks the key up — no `wp.i18n`, no
 * `wp_set_script_translations()` (which is brittle with Vite's hashed bundles).
 *
 * A missing key falls back to the key itself, so a broken/missing dictionary is
 * visible rather than rendering blank.
 */

type Dict = Record<string, string>;

type WindowWithI18n = {
  bsData?: { i18n?: Dict };
  bspData?: { i18n?: Dict };
  // Pro panels can mount into a detached window; fall back to the opener.
  opener?: WindowWithI18n | null;
};

/**
 * Merge the Free + Pro dictionaries from whichever window context is available.
 * Memoised on first use; both dictionaries are static for the page's lifetime.
 */
let cache: Dict | null = null;

function dict(): Dict {
  if (cache) {
    return cache;
  }
  const w = window as unknown as WindowWithI18n;
  const free = w.bsData?.i18n ?? w.opener?.bsData?.i18n ?? {};
  const pro = w.bspData?.i18n ?? w.opener?.bspData?.i18n ?? {};
  cache = { ...free, ...pro };
  return cache;
}

/**
 * Translate a key. Falls back to the key itself when not found.
 */
export function __(key: string): string {
  return dict()[key] ?? key;
}

/**
 * Translate a key with sprintf-style placeholders. Supports both ordered
 * (`%s`, `%d`) and positional (`%1$s`, `%2$d`) tokens, so a translation can
 * reorder arguments for a target language's grammar.
 *
 * @example __f('syncAllN', 3) // "Sync all (3 enabled)"
 */
export function __f(key: string, ...args: (string | number)[]): string {
  let str = __(key);

  // Positional first (%1$s / %2$d …) — a translation may reorder these.
  str = str.replace(/%(\d+)\$[sd]/g, (_m, n: string) => {
    const arg = args[Number(n) - 1];
    return arg === undefined ? _m : String(arg);
  });

  // Then ordered (%s / %d), consumed left to right.
  let i = 0;
  str = str.replace(/%[sd]/g, () => {
    const arg = args[i++];
    return arg === undefined ? '' : String(arg);
  });

  return str;
}
