<script lang="ts">
  import { onMount } from 'svelte';
  import SyncPanel from '../lib/SyncPanel.svelte';

  let {
    restUrl,
    nonce,
    pageUrl,
    inEditor = false,
    manualMode = false,
    postId = 0,
    included = false,
    effective = false,
    includedCount = 0,
    savedCount = 0,
    maxPages = 0,
    unlimited = false,
  }: {
    restUrl: string;
    nonce: string;
    pageUrl: string;
    inEditor?: boolean;
    manualMode?: boolean;
    postId?: number;
    included?: boolean;
    effective?: boolean;
    includedCount?: number;
    savedCount?: number;
    maxPages?: number;
    unlimited?: boolean;
  } = $props();

  const POS_KEY = 'bs_fab_pos';
  const SIZE = 56; // px — button footprint, for viewport clamping.

  let pos = $state<{ x: number; y: number }>(loadPos());
  let open = $state(false);
  let dragging = $state(false);

  function loadPos(): { x: number; y: number } {
    try {
      const raw = localStorage.getItem(POS_KEY);
      if (raw) {
        const p = JSON.parse(raw);
        if (typeof p.x === 'number' && typeof p.y === 'number') return p;
      }
    } catch {
      /* ignore */
    }
    return { x: -1, y: -1 }; // sentinel → default to bottom-right on mount
  }

  function clamp(x: number, y: number): { x: number; y: number } {
    const maxX = Math.max(8, window.innerWidth - SIZE - 8);
    const maxY = Math.max(8, window.innerHeight - SIZE - 8);
    return { x: Math.min(Math.max(8, x), maxX), y: Math.min(Math.max(8, y), maxY) };
  }

  function savePos(): void {
    try {
      localStorage.setItem(POS_KEY, JSON.stringify(pos));
    } catch {
      /* ignore */
    }
  }

  onMount(() => {
    pos = pos.x < 0 ? clamp(window.innerWidth - SIZE - 24, window.innerHeight - SIZE - 24) : clamp(pos.x, pos.y);
    const onResize = (): void => {
      pos = clamp(pos.x, pos.y);
    };
    window.addEventListener('resize', onResize);
    return () => window.removeEventListener('resize', onResize);
  });

  // Drag vs click: a pointer move beyond a small threshold becomes a drag; a tap
  // (no real movement) opens the modal.
  let down = false;
  let moved = false;
  let sx = 0;
  let sy = 0;
  let ox = 0;
  let oy = 0;

  function onPointerDown(e: PointerEvent): void {
    down = true;
    moved = false;
    sx = e.clientX;
    sy = e.clientY;
    ox = pos.x;
    oy = pos.y;
    (e.currentTarget as HTMLElement).setPointerCapture?.(e.pointerId);
  }

  function onPointerMove(e: PointerEvent): void {
    if (!down) return;
    const dx = e.clientX - sx;
    const dy = e.clientY - sy;
    if (!moved && Math.hypot(dx, dy) > 5) {
      moved = true;
      dragging = true;
    }
    if (moved) pos = clamp(ox + dx, oy + dy);
  }

  function onPointerUp(e: PointerEvent): void {
    if (!down) return;
    down = false;
    (e.currentTarget as HTMLElement).releasePointerCapture?.(e.pointerId);
    if (moved) {
      savePos();
      setTimeout(() => (dragging = false), 0);
    } else {
      open = true;
    }
  }
</script>

<button
  class="bs-fab"
  class:bs-fab--dragging={dragging}
  style="left: {pos.x}px; top: {pos.y}px;"
  type="button"
  aria-label="Sync this page"
  title="Sync this page (drag to move)"
  onpointerdown={onPointerDown}
  onpointermove={onPointerMove}
  onpointerup={onPointerUp}
>
  <span class="bs-fab__icon" aria-hidden="true">⟳</span>
  <span class="bs-fab__label">Sync</span>
</button>

<SyncPanel
  bind:open
  {restUrl}
  {nonce}
  {pageUrl}
  {postId}
  {inEditor}
  {manualMode}
  {included}
  {effective}
  {includedCount}
  {savedCount}
  {maxPages}
  {unlimited}
  syncEnabled={true}
/>

<style>
  .bs-fab {
    position: fixed;
    z-index: 2147483000;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 1px;
    width: 56px;
    height: 56px;
    padding: 0;
    border: 0;
    border-radius: 999px;
    background: #2563eb;
    color: #fff;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    cursor: grab;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.28);
    touch-action: none;
    user-select: none;
  }
  .bs-fab:hover {
    background: #1d4ed8;
  }
  .bs-fab--dragging {
    cursor: grabbing;
    box-shadow: 0 8px 22px rgba(0, 0, 0, 0.36);
  }
  .bs-fab__icon {
    font-size: 20px;
    line-height: 1;
  }
  .bs-fab__label {
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.02em;
  }
</style>
