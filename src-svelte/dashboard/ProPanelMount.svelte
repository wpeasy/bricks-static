<script lang="ts">
  import { getPanel } from '../shared/panelRegistry.svelte';
  import type { DestinationDisplay } from '../shared/types';

  // Mounts a registered Pro panel into a Free-owned DOM node. The Pro bundle
  // mounts its own Svelte component via the entry's mount() callback, so the
  // Free bundle never imports a Pro panel. Re-mounts when the destination
  // changes; cleans up on destroy (accordion close / destination switch).
  let {
    entryKey,
    destination,
    onSaved,
  }: {
    entryKey: string;
    destination: DestinationDisplay;
    onSaved: () => void;
  } = $props();

  let host = $state<HTMLElement>();

  $effect(() => {
    const panel = getPanel(entryKey);
    const el = host;
    if (!el || !panel) {
      return;
    }
    const cleanup = panel.mount(el, { destination, onSaved });
    return () => {
      try {
        cleanup?.();
      } catch {
        /* panel already torn down */
      }
    };
  });
</script>

<div bind:this={host}></div>
