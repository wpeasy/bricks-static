/**
 * Dashboard UI preferences, persisted to localStorage (client-side presentation
 * only, no REST). App applies them to its `.ab-ui` root:
 *   - `colorScheme` → `data-theme` (light/dark; omit = auto, follows the OS)
 *   - `compact`     → `.ab-compact` class (tighter density)
 *   - `accent`      → `data-accent` NAMED PRESET (blue = default/no attribute;
 *     green/yellow/purple map 1:1 to ab-ui's built-in accent rules; `custom`
 *     switches to the free-form seeds below).
 *   - `customPrimary` / `customAccent` → inline `--ab-primary` / `--ab-accent`
 *     seeds, applied ONLY when `accent === 'custom'` (driven by ab-ui's
 *     `ThemeCustomizer`, shown only for the Custom preset).
 *   - `distance` / `duration` → `--ab-transition-distance` / `-duration`
 *     (also custom-only — they live inside the ThemeCustomizer).
 * Reactive: components reading `uiPrefs.*` re-render when it changes.
 */

export type ColorScheme = 'auto' | 'light' | 'dark';
export type Accent = 'blue' | 'green' | 'yellow' | 'purple' | 'custom';

export interface UiPrefs {
  colorScheme: ColorScheme;
  compact: boolean;
  /** Named theme preset — ab-ui `data-accent`. `custom` uses the seeds below. */
  accent: Accent;
  /** ThemeCustomizer seeds — only applied when `accent === 'custom'`. */
  customPrimary: string;
  customAccent: string;
  distance: number;
  duration: number;
}

const KEY = 'bs_ui_prefs';

// Defaults mirror ab-ui's ThemeCustomizer / token defaults.
const DEFAULTS: UiPrefs = {
  colorScheme: 'auto',
  compact: false,
  accent: 'blue',
  customPrimary: '#3b6fd4',
  customAccent: '#1f9fc4',
  distance: 15,
  duration: 350,
};

const SCHEMES: ColorScheme[] = ['auto', 'light', 'dark'];
const ACCENTS: Accent[] = ['blue', 'green', 'yellow', 'purple', 'custom'];

function load(): UiPrefs {
  try {
    const raw = JSON.parse(localStorage.getItem(KEY) || '{}') as Partial<UiPrefs>;
    return {
      colorScheme: SCHEMES.includes(raw.colorScheme as ColorScheme) ? (raw.colorScheme as ColorScheme) : DEFAULTS.colorScheme,
      compact: typeof raw.compact === 'boolean' ? raw.compact : DEFAULTS.compact,
      accent: ACCENTS.includes(raw.accent as Accent) ? (raw.accent as Accent) : DEFAULTS.accent,
      customPrimary: typeof raw.customPrimary === 'string' ? raw.customPrimary : DEFAULTS.customPrimary,
      customAccent: typeof raw.customAccent === 'string' ? raw.customAccent : DEFAULTS.customAccent,
      distance: typeof raw.distance === 'number' ? raw.distance : DEFAULTS.distance,
      duration: typeof raw.duration === 'number' ? raw.duration : DEFAULTS.duration,
    };
  } catch {
    return { ...DEFAULTS };
  }
}

export const uiPrefs = $state<UiPrefs>(load());

export function persistPrefs(): void {
  try {
    localStorage.setItem(
      KEY,
      JSON.stringify({
        colorScheme: uiPrefs.colorScheme,
        compact: uiPrefs.compact,
        accent: uiPrefs.accent,
        customPrimary: uiPrefs.customPrimary,
        customAccent: uiPrefs.customAccent,
        distance: uiPrefs.distance,
        duration: uiPrefs.duration,
      }),
    );
  } catch {
    /* localStorage unavailable (private mode) — prefs just won't persist. */
  }
}

/** `undefined` for auto so the attribute is omitted (ab-ui treats a missing
    `data-theme` as auto, following the OS). */
export function themeAttr(): ColorScheme | undefined {
  return uiPrefs.colorScheme === 'auto' ? undefined : uiPrefs.colorScheme;
}

/** `undefined` for the base 'blue' preset so `data-accent` is omitted (ab-ui's
    default accent). green/yellow/purple map 1:1; 'custom' keeps the attribute
    and additionally applies the inline `--ab-primary`/`--ab-accent` seeds. */
export function accentAttr(): Accent | undefined {
  return uiPrefs.accent === 'blue' ? undefined : uiPrefs.accent;
}
