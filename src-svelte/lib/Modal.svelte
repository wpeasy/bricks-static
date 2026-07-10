<script lang="ts">
  // Thin wrapper over ab-ui's <Modal>, preserving this project's (open/title/wide)
  // API so existing call sites don't change.
  //
  // portal={false}: ab-ui's Modal defaults to portalling the dialog to <body>,
  // but the portal action relocates the node, which makes Svelte SKIP the dialog's
  // `transition:scaleFade` intro — so the modal would pop in with no animation.
  // Rendering in place (like the ab-bricks-productivity modals do) lets the open
  // transition play. We only mount these in the wp-admin main window, never inside
  // the Bricks preview iframe, so there's no transformed/clipped ancestor to escape.
  import type { Snippet } from 'svelte';
  import { Modal as AbModal } from '@wpeasy/ab-ui';

  let {
    open = $bindable(false),
    title,
    wide = false,
    size,
    children,
  }: {
    open: boolean;
    title: string;
    /** Near-fullscreen for content that needs room, e.g. long lists. */
    wide?: boolean;
    /** Explicit ab-ui size preset — overrides `wide` when set (e.g. 'lg' for a
        wizard, which needs more than 'md' but not 'near'-fullscreen). */
    size?: 'sm' | 'md' | 'lg' | 'near' | 'full';
    children: Snippet;
  } = $props();
</script>

<AbModal bind:open {title} size={size ?? (wide ? 'near' : 'md')} portal={false}>
  {@render children()}
</AbModal>
