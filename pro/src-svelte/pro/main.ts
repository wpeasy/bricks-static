/**
 * Bricks Static Pro dashboard bundle.
 *
 * Loaded after the Free dashboard bundle (WordPress script dependency), it
 * registers the Pro replacement panels into the Free dashboard via the
 * window.BS registry. Each panel is mounted with the Pro bundle's OWN Svelte
 * runtime into a Free-owned DOM node, so the Free bundle never imports a Pro
 * component.
 */

import { mount, unmount } from 'svelte';
import type { DestinationDisplay } from '../../../src-svelte/shared/types';
import MediaReplacer from './MediaReplacer.svelte';
import LinkReplacer from './LinkReplacer.svelte';
import VideoReplacer from './VideoReplacer.svelte';

interface PanelProps {
  destination: DestinationDisplay;
  onSaved: () => void;
}

interface PanelEntry {
  key: string;
  badge?: (d: DestinationDisplay) => number;
  mount: (target: HTMLElement, props: PanelProps) => () => void;
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
function register(key: string, Comp: any, badge: (d: DestinationDisplay) => number): void {
  const reg = (window as unknown as { BS?: { registerReplacementPanel?: (e: PanelEntry) => void } })
    .BS?.registerReplacementPanel;
  if (typeof reg !== 'function') {
    return;
  }
  reg({
    key,
    badge,
    mount(target, props) {
      const app = mount(Comp, { target, props });
      return () => {
        unmount(app);
      };
    },
  });
}

register('media', MediaReplacer, (d) => d.mediaReplacements?.length ?? 0);
register('links', LinkReplacer, (d) => d.linkReplacements?.length ?? 0);
register('videos', VideoReplacer, (d) => d.videoReplacements?.length ?? 0);
