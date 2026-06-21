/**
 * Registry of Pro replacement panels injected into the Free dashboard.
 *
 * The Free bundle owns the accordion and a mount point for each catalogue row,
 * but never imports a Pro panel. The separately-built Pro bundle calls
 * `window.BS.registerReplacementPanel(...)` for each panel it provides; the
 * dashboard then mounts those into the matching rows. Reactive, so a panel
 * registered after first paint still appears.
 */

import type { DestinationDisplay } from './types';

export interface PanelEntry {
  /** Matches a ReplacementKey in the catalogue (e.g. 'media'). */
  key: string;
  /** Optional live count for the row's badge. */
  badge?: (destination: DestinationDisplay) => number;
  /**
   * Mount the Pro panel into `target` (a Free-owned DOM node) using the Pro
   * bundle's own Svelte runtime. Returns a cleanup function called on unmount.
   */
  mount: (
    target: HTMLElement,
    props: { destination: DestinationDisplay; onSaved: () => void },
  ) => () => void;
}

/** Reactive map of registered panels, keyed by panel key. */
const registered = $state<Record<string, PanelEntry>>({});

/** Register (or replace) a Pro panel. Exposed on window.BS for the Pro bundle. */
export function registerReplacementPanel(entry: PanelEntry): void {
  if (entry && typeof entry.key === 'string' && typeof entry.mount === 'function') {
    registered[entry.key] = entry;
  }
}

/** Look up a registered panel by key (reactive read). */
export function getPanel(key: string): PanelEntry | undefined {
  return registered[key];
}
