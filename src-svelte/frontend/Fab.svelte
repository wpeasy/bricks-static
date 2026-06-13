<script lang="ts">
  import { onMount } from 'svelte';

  let {
    restUrl,
    nonce,
    pageUrl,
    inEditor = false,
  }: { restUrl: string; nonce: string; pageUrl: string; inEditor?: boolean } = $props();

  interface Target {
    id: string;
    name: string;
    pushed: boolean;
  }
  interface PageStatus {
    rel: string | null;
    targets: Target[];
    canSync: boolean;
  }
  interface Snap {
    phase: string;
    message?: string;
    running?: boolean;
    driver?: 'cli' | 'browser';
    counts?: { pagesDone?: number; assetsDone?: number; uploaded?: number };
    totals?: { pages?: number; assets?: number; uploads?: number };
  }

  const POS_KEY = 'bs_fab_pos';
  const SIZE = 56; // px — button footprint, for viewport clamping.

  let pos = $state<{ x: number; y: number }>(loadPos());
  let open = $state(false);
  let dragging = $state(false);

  let status = $state<PageStatus | null>(null);
  let statusErr = $state('');
  let snap = $state<Snap | null>(null);
  let starting = $state(false);
  let savingEditor = $state(false);
  // Hide the action button once this page has been synced — until the modal is
  // reopened (the work is already done; re-clicking would just repeat it).
  let syncedThisSession = $state(false);

  let running = $derived(!!snap?.running);
  let finished = $derived(!!snap && !snap.running && ['done', 'error', 'cancelled'].includes(snap.phase));
  let tone = $derived(snap?.phase === 'error' || snap?.phase === 'cancelled' ? 'warn' : snap?.phase === 'done' ? 'ok' : 'active');

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
      void openModal();
    }
  }

  async function api(path: string, init?: RequestInit): Promise<any> {
    const res = await fetch(restUrl + path, {
      ...init,
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce, ...(init?.headers ?? {}) },
    });
    if (!res.ok && res.status !== 200) {
      throw new Error('Request failed (' + res.status + ')');
    }
    return res.json();
  }

  const sleep = (ms: number): Promise<void> => new Promise((r) => setTimeout(r, ms));

  async function openModal(): Promise<void> {
    open = true;
    statusErr = '';
    syncedThisSession = false;
    if (!running) snap = null;
    try {
      status = await api('/sync/page-status?url=' + encodeURIComponent(pageUrl));
    } catch (e) {
      statusErr = (e as Error).message;
    }
  }

  // In the Bricks editor, save the open document first (so the static render
  // reflects unsaved edits): trigger Bricks' own save (Cmd/Ctrl+S) and wait for
  // its `bricks_save_post` request to finish, with a timeout fallback so we never
  // hang if there was nothing to save.
  function saveEditor(timeoutMs = 12000): Promise<void> {
    return new Promise((resolve) => {
      const Native = window.XMLHttpRequest;
      let settled = false;
      const finish = (): void => {
        if (settled) return;
        settled = true;
        window.XMLHttpRequest = Native;
        clearTimeout(timer);
        resolve();
      };
      const timer = setTimeout(finish, timeoutMs);

      const matches = (s: unknown): boolean => typeof s === 'string' && s.indexOf('bricks_save_post') !== -1;

      // Watch outgoing XHRs for the save request; resolve when it completes.
      function Wrapped(this: unknown): XMLHttpRequest {
        const xhr = new Native();
        let isSave = false;
        const open = xhr.open.bind(xhr);
        const send = xhr.send.bind(xhr);
        xhr.open = function (method: string, url: string | URL, ...rest: unknown[]): void {
          if (matches(String(url))) isSave = true;
          // @ts-expect-error — forwarding native signature
          return open(method, url, ...rest);
        };
        xhr.send = function (body?: Document | XMLHttpRequestBodyInit | null): void {
          try {
            if (matches(typeof body === 'string' ? body : '') || (body instanceof FormData && matches(String(body.get('action'))))) {
              isSave = true;
            }
          } catch {
            /* ignore */
          }
          if (isSave) xhr.addEventListener('loadend', finish);
          return send(body);
        };
        return xhr;
      }
      window.XMLHttpRequest = Wrapped as unknown as typeof XMLHttpRequest;

      // Fire Bricks' save shortcut on the document (its handler is global).
      const ev = new KeyboardEvent('keydown', {
        key: 's',
        code: 'KeyS',
        keyCode: 83,
        which: 83,
        ctrlKey: true,
        metaKey: true,
        bubbles: true,
        cancelable: true,
      });
      document.dispatchEvent(ev);
    });
  }

  async function syncPage(): Promise<void> {
    starting = true;
    snap = null;
    try {
      if (inEditor) {
        savingEditor = true;
        try {
          await saveEditor();
        } finally {
          savingEditor = false;
        }
      }

      const started: Snap = await api('/sync/page', { method: 'POST', body: JSON.stringify({ url: pageUrl }) });
      snap = started;
      if (started.phase === 'error') return;

      const claim = await api('/sync/claim', { method: 'POST' });
      const browserDrives = claim?.owner === 'browser';

      while (snap?.running) {
        snap = browserDrives ? await api('/sync/tick', { method: 'POST' }) : (await sleep(1200), await api('/sync'));
      }
      // Done — hide the action button (the page is synced) until the modal reopens.
      if (snap?.phase === 'done') syncedThisSession = true;
      // Refresh the per-page pushed state after the run.
      try {
        status = await api('/sync/page-status?url=' + encodeURIComponent(pageUrl));
      } catch {
        /* keep prior status */
      }
    } catch (e) {
      snap = { phase: 'error', message: (e as Error).message };
    } finally {
      starting = false;
    }
  }

  function pageLabel(): string {
    try {
      return new URL(pageUrl).pathname || '/';
    } catch {
      return pageUrl;
    }
  }

  function pct(done?: number, total?: number): number {
    return total && total > 0 ? Math.min(100, Math.round(((done ?? 0) / total) * 100)) : 0;
  }
</script>

<button
  class="bs-fab"
  class:bs-fab--dragging={dragging}
  class:bs-fab--busy={running}
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

{#if open}
  <!-- svelte-ignore a11y_click_events_have_key_events, a11y_no_static_element_interactions -->
  <div class="bs-fabm" onclick={() => (open = false)}>
    <div class="bs-fabm__box" role="dialog" aria-modal="true" aria-label="Sync this page" tabindex="-1" onclick={(e) => e.stopPropagation()}>
      <div class="bs-fabm__head">
        <h2 class="bs-fabm__title">Sync this page</h2>
        <button type="button" class="bs-fabm__x" aria-label="Close" onclick={() => (open = false)}>×</button>
      </div>

      <div class="bs-fabm__body">
        <p class="bs-fabm__page">{pageLabel()}</p>

        {#if statusErr}
          <p class="bs-fabm__msg bs-fabm__msg--err">{statusErr}</p>
        {:else if status === null}
          <p class="bs-fabm__muted">Checking…</p>
        {:else if !status.canSync}
          <p class="bs-fabm__msg bs-fabm__msg--err">
            No destinations are enabled for single-page sync. Turn on “Include in single-page sync” on a destination in the
            admin dashboard.
          </p>
        {:else}
          <ul class="bs-fabm__targets">
            {#each status.targets as t (t.id)}
              <li class="bs-fabm__target">
                <span class="bs-fabm__dot bs-fabm__dot--{t.pushed ? 'on' : 'off'}"></span>
                <span class="bs-fabm__tname">{t.name}</span>
                <span class="bs-fabm__tstate">{t.pushed ? 'Pushed' : 'Not pushed'}</span>
              </li>
            {/each}
          </ul>
        {/if}

        {#if snap}
          <div class="bs-fabm__progress bs-fabm__progress--{tone}">
            <div class="bs-fabm__prow">
              <span class="bs-fabm__phase">{snap.phase}</span>
              {#if running}<span class="bs-fabm__spin" aria-hidden="true">⟳</span>{/if}
            </div>
            {#if snap.message}<p class="bs-fabm__muted">{snap.message}</p>{/if}
            {#if running || finished}
              <div class="bs-fabm__bar"><div class="bs-fabm__fill" style="width: {pct(snap.counts?.uploaded ?? snap.counts?.pagesDone, snap.totals?.uploads ?? snap.totals?.pages)}%"></div></div>
              <p class="bs-fabm__counts">
                {snap.counts?.pagesDone ?? 0}/{snap.totals?.pages ?? 0} pages · {snap.counts?.uploaded ?? 0}/{snap.totals?.uploads ?? 0} uploaded
              </p>
            {/if}
          </div>
        {/if}
      </div>

      <div class="bs-fabm__foot">
        {#if syncedThisSession}<span class="bs-fabm__done">✓ Synced</span>{/if}
        <button type="button" class="bs-fabm__btn" onclick={() => (open = false)}>Close</button>
        {#if !syncedThisSession}
          <button
            type="button"
            class="bs-fabm__btn bs-fabm__btn--primary"
            onclick={syncPage}
            disabled={starting || running || !status?.canSync}
          >
            {savingEditor ? 'Saving editor…' : running || starting ? 'Syncing…' : inEditor ? 'Save & sync this page' : 'Sync this page'}
          </button>
        {/if}
      </div>
    </div>
  </div>
{/if}

<style>
  /* Fully self-contained: no global :root / color-scheme, scoped values only, so
     the host page's styles (forms, theme) are never affected. */
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
  .bs-fab--busy .bs-fab__icon {
    animation: bs-fab-spin 0.9s linear infinite;
  }
  .bs-fab__label {
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.02em;
  }

  .bs-fabm {
    position: fixed;
    inset: 0;
    z-index: 2147483001;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    background: rgba(15, 23, 42, 0.5);
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  }
  .bs-fabm__box {
    width: min(420px, 100%);
    max-height: 85vh;
    overflow: auto;
    background: #fff;
    color: #0f172a;
    border-radius: 12px;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.35);
  }
  .bs-fabm__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 18px;
    border-bottom: 1px solid #e5e7eb;
  }
  .bs-fabm__title {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
  }
  .bs-fabm__x {
    border: 0;
    background: none;
    font-size: 22px;
    line-height: 1;
    color: #64748b;
    cursor: pointer;
  }
  .bs-fabm__body {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 18px;
  }
  .bs-fabm__page {
    margin: 0;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 13px;
    word-break: break-all;
    color: #0f172a;
  }
  .bs-fabm__muted {
    margin: 0;
    font-size: 13px;
    color: #64748b;
  }
  .bs-fabm__msg {
    margin: 0;
    font-size: 13px;
  }
  .bs-fabm__msg--err {
    color: #b91c1c;
  }
  .bs-fabm__targets {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 6px;
  }
  .bs-fabm__target {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
  }
  .bs-fabm__tname {
    flex: 1;
    font-weight: 500;
  }
  .bs-fabm__tstate {
    color: #64748b;
    font-size: 12px;
  }
  .bs-fabm__dot {
    width: 9px;
    height: 9px;
    border-radius: 999px;
    background: #cbd5e1;
  }
  .bs-fabm__dot--on {
    background: #16a34a;
  }
  .bs-fabm__dot--off {
    background: #f59e0b;
  }
  .bs-fabm__progress {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 10px 12px;
    border-radius: 8px;
    background: #f1f5f9;
  }
  .bs-fabm__progress--ok {
    background: #ecfdf5;
  }
  .bs-fabm__progress--warn {
    background: #fef2f2;
  }
  .bs-fabm__prow {
    display: flex;
    align-items: center;
    justify-content: space-between;
  }
  .bs-fabm__phase {
    font-size: 13px;
    font-weight: 600;
    text-transform: capitalize;
  }
  .bs-fabm__spin {
    animation: bs-fab-spin 0.9s linear infinite;
    color: #2563eb;
  }
  .bs-fabm__bar {
    height: 6px;
    border-radius: 999px;
    background: #e2e8f0;
    overflow: hidden;
  }
  .bs-fabm__fill {
    height: 100%;
    background: #2563eb;
    transition: width 0.2s ease;
  }
  .bs-fabm__counts {
    margin: 0;
    font-size: 12px;
    color: #64748b;
  }
  .bs-fabm__foot {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
    padding: 14px 18px;
    border-top: 1px solid #e5e7eb;
  }
  .bs-fabm__done {
    margin-right: auto;
    font-size: 13px;
    font-weight: 600;
    color: #16a34a;
  }
  .bs-fabm__btn {
    padding: 8px 14px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    background: #fff;
    color: #0f172a;
    font: inherit;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
  }
  .bs-fabm__btn--primary {
    background: #2563eb;
    border-color: #2563eb;
    color: #fff;
  }
  .bs-fabm__btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }

  @keyframes bs-fab-spin {
    to {
      transform: rotate(360deg);
    }
  }
</style>
