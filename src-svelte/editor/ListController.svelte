<script lang="ts">
  import { onMount, untrack } from 'svelte';
  import SyncPanel from '../lib/SyncPanel.svelte';

  // Wires the server-rendered "Static" column cells (.bs-static-cell buttons) to
  // one shared sync modal, and repaints a cell after its include/sync changes.
  let {
    restUrl,
    nonce,
    manualMode = false,
    fabEnabled = true,
    includedCount = 0,
  }: {
    restUrl: string;
    nonce: string;
    manualMode?: boolean;
    fabEnabled?: boolean;
    includedCount?: number;
  } = $props();

  let open = $state(false);
  let count = $state(untrack(() => includedCount));
  let ctx = $state<{ postId: number; pageUrl: string; included: boolean }>({ postId: 0, pageUrl: '', included: false });
  let activeBtn: HTMLButtonElement | null = null;

  function paint(btn: HTMLButtonElement, state: string, synced: boolean): void {
    btn.dataset.state = state;
    btn.dataset.synced = synced ? '1' : '0';
    if (state === 'excluded') {
      btn.innerHTML = '<span class="bs-static-x" aria-hidden="true">✕</span>';
    } else if (state === 'unknown') {
      btn.innerHTML = '<span class="bs-static-q" aria-hidden="true">?</span>';
    } else {
      btn.innerHTML =
        '<span class="bs-static-dot bs-static-dot--' + (synced ? 'ok' : 'warn') + '" aria-hidden="true"></span>' +
        '<span class="bs-static-ico" aria-hidden="true">⟳</span>';
    }
  }

  function openFor(btn: HTMLButtonElement): void {
    activeBtn = btn;
    ctx = {
      postId: Number(btn.dataset.post) || 0,
      pageUrl: btn.dataset.url || '',
      included: btn.dataset.included === '1',
    };
    open = true;
  }

  function onIncludeChange(included: boolean, newCount: number): void {
    count = newCount;
    if (!activeBtn) return;
    activeBtn.dataset.included = included ? '1' : '0';
    // In manual mode the include switch directly flips the cell; a freshly
    // included page is "out of date" until it's synced.
    if (manualMode) paint(activeBtn, included ? 'included' : 'excluded', false);
  }

  function onSynced(): void {
    if (activeBtn) paint(activeBtn, 'included', true);
  }

  // Repaint the cells of pages just bulk-included via the "Include all" cascade
  // (newly included → "out of date" amber until they're synced).
  function onBulkIncluded(postIds: number[]): void {
    for (const id of postIds) {
      const btn = document.querySelector<HTMLButtonElement>(`.bs-static-cell[data-post="${id}"]`);
      if (btn) {
        btn.dataset.included = '1';
        paint(btn, 'included', false);
      }
    }
  }

  onMount(() => {
    const cells = Array.from(document.querySelectorAll<HTMLButtonElement>('.bs-static-cell'));
    const offs: Array<() => void> = [];
    for (const btn of cells) {
      const handler = (): void => openFor(btn);
      btn.addEventListener('click', handler);
      offs.push(() => btn.removeEventListener('click', handler));
    }
    return () => {
      for (const off of offs) off();
    };
  });
</script>

<SyncPanel
  bind:open
  {restUrl}
  {nonce}
  postId={ctx.postId}
  pageUrl={ctx.pageUrl}
  {manualMode}
  included={ctx.included}
  includedCount={count}
  syncEnabled={fabEnabled}
  {onIncludeChange}
  {onBulkIncluded}
  {onSynced}
/>

<style>
  /* The cells are server-rendered into the WP list table (outside this
     component), so style them globally. Hardcoded colours read on the admin's
     light + dark schemes. */
  :global(.bs-static-cell) {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 2px 7px;
    border: 1px solid transparent;
    border-radius: 6px;
    background: none;
    cursor: pointer;
    line-height: 1;
  }
  :global(.bs-static-cell:hover) {
    background: rgba(127, 127, 127, 0.14);
  }
  :global(.bs-static-dot) {
    display: inline-block;
    width: 9px;
    height: 9px;
    border-radius: 999px;
    background: #cbd5e1;
  }
  :global(.bs-static-dot--ok) {
    background: #16a34a;
  }
  :global(.bs-static-dot--warn) {
    background: #f59e0b;
  }
  :global(.bs-static-ico) {
    font-size: 13px;
    line-height: 1;
    color: #2563eb;
  }
  :global(.bs-static-x) {
    font-size: 12px;
    color: #9aa0a6;
  }
  :global(.bs-static-q) {
    font-weight: 700;
    color: #9aa0a6;
  }
  :global(.bs-static-na) {
    color: #9aa0a6;
  }
</style>
