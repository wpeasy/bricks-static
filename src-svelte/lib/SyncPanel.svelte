<script lang="ts">
  import { untrack } from 'svelte';
  import { autoContrast } from '@wpeasy/ab-ui';
  import { outboundExcluded, inboundIncluded, includeBulk, type LinkPage } from '../shared/linkCheck';
  import { uiPrefs, themeAttr, accentAttr } from '../shared/uiPrefs.svelte';

  // The shared "Sync this page" modal — used by the frontend FAB and the admin
  // Pages/Posts list column. Rendered inside an `.ab-ui` scope and themed off the
  // dashboard's saved theme (light/dark + accent), so it matches the dashboard.
  // Colours use `--ab-*` tokens with hardcoded fallbacks, so it still renders if
  // the ab-ui stylesheet isn't present in a given mount context.
  let {
    open = $bindable(false),
    restUrl,
    nonce,
    pageUrl,
    postId = 0,
    inEditor = false,
    manualMode = false,
    included = false,
    effective = false,
    includedCount = 0,
    savedCount = 0,
    maxPages = 0,
    unlimited = false,
    syncEnabled = true,
    onClose,
    onIncludeChange,
    onBulkIncluded,
    onSynced,
  }: {
    open?: boolean;
    restUrl: string;
    nonce: string;
    pageUrl: string;
    postId?: number;
    inEditor?: boolean;
    manualMode?: boolean;
    included?: boolean;
    effective?: boolean;
    includedCount?: number;
    savedCount?: number;
    maxPages?: number;
    unlimited?: boolean;
    syncEnabled?: boolean;
    onClose?: () => void;
    onIncludeChange?: (included: boolean, count: number) => void;
    onBulkIncluded?: (postIds: number[]) => void;
    onSynced?: () => void;
  } = $props();

  interface Target {
    id: string;
    name: string;
    pushed: boolean;
    inSync: boolean;
  }
  interface PageStatus {
    rel: string | null;
    targets: Target[];
    canSync: boolean;
    published: boolean;
    upToDate: boolean;
  }
  interface Snap {
    phase: string;
    message?: string;
    running?: boolean;
    driver?: 'cli' | 'browser';
    counts?: { pagesDone?: number; assetsDone?: number; uploaded?: number };
    totals?: { pages?: number; assets?: number; uploads?: number };
    zipUnavailable?: boolean;
    packageFallback?: string;
  }

  let status = $state<PageStatus | null>(null);
  let statusErr = $state('');
  let snap = $state<Snap | null>(null);
  let starting = $state(false);
  let savingEditor = $state(false);
  let syncedThisSession = $state(false);

  let includeOn = $state(false);
  let includeCount = $state(0);
  let savedTotal = $state(0);
  let savingInclude = $state(false);

  // This page is saved-included but over the Free cap, so it won't export.
  let isOverLimit = $derived(manualMode && includeOn && !effective && !unlimited);

  // Link-integrity prompt after a manual include/exclude.
  let linkPrompt = $state<{ kind: 'out' | 'in'; pages: LinkPage[] } | null>(null);
  let linkBusy = $state(false);
  let linkSkipped = $state(0);

  let running = $derived(!!snap?.running);
  let finished = $derived(!!snap && !snap.running && ['done', 'error', 'cancelled'].includes(snap.phase));
  let tone = $derived(snap?.phase === 'error' || snap?.phase === 'cancelled' ? 'warn' : snap?.phase === 'done' ? 'ok' : 'active');
  let atLimit = $derived(!unlimited && !includeOn && includeCount >= maxPages);

  // Re-seed local state whenever a new post is targeted (the list reuses one
  // panel across rows).
  $effect(() => {
    void postId; // re-seed whenever a new post is targeted
    includeOn = untrack(() => included);
    includeCount = untrack(() => includedCount);
    savedTotal = untrack(() => savedCount);
    linkPrompt = null;
    linkSkipped = 0;
  });

  // Load the page's status whenever the modal opens.
  $effect(() => {
    if (!open) return;
    untrack(() => {
      syncedThisSession = false;
      if (!running) snap = null;
      void loadStatus();
    });
  });

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

  function close(): void {
    open = false;
    onClose?.();
  }

  async function loadStatus(): Promise<void> {
    statusErr = '';
    status = null;
    if (!pageUrl) return;
    try {
      status = await api(pageStatusPath());
    } catch (e) {
      statusErr = (e as Error).message;
    }
  }

  function pageStatusPath(): string {
    return '/sync/page-status?url=' + encodeURIComponent(pageUrl) + (postId > 0 ? '&postId=' + postId : '');
  }

  async function saveInclude(value: boolean): Promise<void> {
    const previous = includeOn;
    includeOn = value; // optimistic
    savingInclude = true;
    try {
      const r = await api('/editor/include', { method: 'POST', body: JSON.stringify({ postId, include: value }) });
      includeOn = !!r.included;
      if (typeof r.includedCount === 'number') includeCount = r.includedCount;
      if (typeof r.savedCount === 'number') savedTotal = r.savedCount;
      onIncludeChange?.(includeOn, includeCount);
      void runLinkCheck();
    } catch {
      includeOn = previous;
    } finally {
      savingInclude = false;
    }
  }

  async function runLinkCheck(): Promise<void> {
    linkSkipped = 0;
    try {
      if (includeOn) {
        const r = await outboundExcluded(restUrl, nonce, postId);
        linkPrompt = r.pages?.length ? { kind: 'out', pages: r.pages } : null;
      } else {
        const r = await inboundIncluded(restUrl, nonce, postId);
        linkPrompt = r.pages?.length ? { kind: 'in', pages: r.pages } : null;
      }
    } catch {
      linkPrompt = null;
    }
  }

  async function includeAll(): Promise<void> {
    if (!linkPrompt || linkPrompt.kind !== 'out') return;
    linkBusy = true;
    try {
      const r = await includeBulk(restUrl, nonce, linkPrompt.pages.map((p) => p.postId));
      if (typeof r.includedCount === 'number') {
        includeCount = r.includedCount;
        onIncludeChange?.(includeOn, includeCount);
      }
      if (typeof r.savedCount === 'number') savedTotal = r.savedCount;
      onBulkIncluded?.(r.included ?? []);
      linkSkipped = r.skipped?.length ?? 0;
      linkPrompt = null;
    } catch {
      /* ignore */
    } finally {
      linkBusy = false;
    }
  }

  // In the Bricks editor, save the open document first so the static render
  // reflects unsaved edits.
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

      function Wrapped(this: unknown): XMLHttpRequest {
        const xhr = new Native();
        let isSave = false;
        const xopen = xhr.open.bind(xhr);
        const send = xhr.send.bind(xhr);
        xhr.open = function (method: string, url: string | URL, ...rest: unknown[]): void {
          if (matches(String(url))) isSave = true;
          // @ts-expect-error — forwarding native signature
          return xopen(method, url, ...rest);
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

      const ev = new KeyboardEvent('keydown', { key: 's', code: 'KeyS', keyCode: 83, which: 83, ctrlKey: true, metaKey: true, bubbles: true, cancelable: true });
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
      if (snap?.phase === 'done') {
        syncedThisSession = true;
        onSynced?.();
      }
      try {
        status = await api(pageStatusPath());
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

{#if open}
  <!-- svelte-ignore a11y_click_events_have_key_events, a11y_no_static_element_interactions -->
  <div
    class="bs-sp ab-ui"
    class:ab-compact={uiPrefs.compact}
    style:--ab-primary={uiPrefs.accent === 'custom' ? uiPrefs.customPrimary : undefined}
    style:--ab-accent={uiPrefs.accent === 'custom' ? uiPrefs.customAccent : undefined}
    data-theme={themeAttr()}
    data-accent={accentAttr()}
    use:autoContrast
    onclick={close}
  >
    <div class="bs-sp__box" role="dialog" aria-modal="true" aria-label="Sync this page" tabindex="-1" onclick={(e) => e.stopPropagation()}>
      <div class="bs-sp__head">
        <h2 class="bs-sp__title">Sync this page</h2>
        <button type="button" class="bs-sp__x" aria-label="Close" onclick={close}>×</button>
      </div>

      <div class="bs-sp__body">
        <p class="bs-sp__page">{pageLabel()}</p>

        {#if manualMode && postId > 0}
          <label class="bs-sp__include">
            <input type="checkbox" checked={includeOn} disabled={savingInclude || atLimit} onchange={(e) => saveInclude(e.currentTarget.checked)} />
            <span>Include in static export</span>
          </label>
          <p class="bs-sp__inccount">
            {unlimited ? `${includeCount} pages included` : `${includeCount} of ${maxPages} pages included`}
          </p>
          {#if !unlimited && savedTotal > includeCount}
            <p class="bs-sp__inccount">{savedTotal - includeCount} more saved — over your Free limit.</p>
          {/if}
          {#if isOverLimit}
            <p class="bs-sp__msg bs-sp__msg--err">This page is saved but over your Free limit — it won’t export until you upgrade or free up room.</p>
          {:else if atLimit}
            <p class="bs-sp__msg bs-sp__msg--err">Free plan page limit reached — exclude another page, or upgrade to Pro.</p>
          {/if}

          {#if linkPrompt}
            <div class="bs-sp__links bs-sp__links--{linkPrompt.kind}">
              {#if linkPrompt.kind === 'out'}
                <p class="bs-sp__msg">This page links to {linkPrompt.pages.length} page{linkPrompt.pages.length === 1 ? '' : 's'} not included:</p>
              {:else}
                <p class="bs-sp__msg bs-sp__msg--err">{linkPrompt.pages.length} included page{linkPrompt.pages.length === 1 ? '' : 's'} link here — they'll have dead links:</p>
              {/if}
              <ul class="bs-sp__linklist">
                {#each linkPrompt.pages as p (p.postId)}<li>{p.title}</li>{/each}
              </ul>
              {#if linkPrompt.kind === 'out'}
                <button type="button" class="bs-sp__btn bs-sp__btn--primary bs-sp__linkbtn" onclick={includeAll} disabled={linkBusy}>
                  {linkBusy ? 'Including…' : 'Include all'}
                </button>
              {:else}
                <p class="bs-sp__notefoot">Based on the last check/sync — run a check to refresh.</p>
              {/if}
            </div>
          {/if}
          {#if linkSkipped > 0}
            <p class="bs-sp__msg bs-sp__msg--err">{linkSkipped} couldn't be included (Free limit reached).</p>
          {/if}
        {/if}

        {#if statusErr}
          <p class="bs-sp__msg bs-sp__msg--err">{statusErr}</p>
        {:else if status === null}
          <p class="bs-sp__muted">Checking…</p>
        {:else if !status.published}
          <p class="bs-sp__note">Publish this page to sync it.</p>
        {:else if !status.canSync}
          <p class="bs-sp__msg bs-sp__msg--err">No destinations are enabled for single-page sync.</p>
        {:else}
          <ul class="bs-sp__targets">
            {#each status.targets as t (t.id)}
              <li class="bs-sp__target">
                <span class="bs-sp__dot bs-sp__dot--{t.inSync ? 'on' : 'off'}"></span>
                <span class="bs-sp__tname">{t.name}</span>
                <span class="bs-sp__tstate">{t.inSync ? 'In sync' : t.pushed ? 'Needs sync' : 'Not pushed'}</span>
              </li>
            {/each}
          </ul>
          {#if status.upToDate && !syncedThisSession}
            <p class="bs-sp__note bs-sp__note--ok">This page is already up to date on every destination — nothing to sync.</p>
          {/if}
        {/if}

        {#if snap}
          <div class="bs-sp__progress bs-sp__progress--{tone}">
            <div class="bs-sp__prow">
              <span class="bs-sp__phase">{snap.phase}</span>
              {#if running}<span class="bs-sp__spin" aria-hidden="true">⟳</span>{/if}
            </div>
            {#if snap.message}<p class="bs-sp__muted">{snap.message}</p>{/if}
            {#if running || finished}
              <div class="bs-sp__bar"><div class="bs-sp__fill" style="width: {pct(snap.counts?.uploaded ?? snap.counts?.pagesDone, snap.totals?.uploads ?? snap.totals?.pages)}%"></div></div>
              <p class="bs-sp__counts">
                {snap.counts?.pagesDone ?? 0}/{snap.totals?.pages ?? 0} pages · {snap.counts?.uploaded ?? 0}/{snap.totals?.uploads ?? 0} uploaded
              </p>
            {/if}
          </div>
        {/if}

        {#if snap?.packageFallback}
          <p class="bs-sp__notice">
            The faster one-shot package deploy didn’t complete — {snap.packageFallback}. Files were uploaded individually instead (same result, just slower).
          </p>
        {:else if snap?.zipUnavailable}
          <p class="bs-sp__notice">
            Uploaded file-by-file: the PHP runtime running this sync has no Zip (ZipArchive) extension, so the faster single-package deploy wasn’t available. Common with Local’s bundled WP-CLI PHP — most production hosts include Zip and package automatically.
          </p>
        {/if}
      </div>

      <div class="bs-sp__foot">
        {#if syncedThisSession}<span class="bs-sp__done">✓ Synced</span>{/if}
        <button type="button" class="bs-sp__btn" onclick={close}>Close</button>
        {#if syncEnabled && !syncedThisSession && status?.published !== false}
          <button
            type="button"
            class="bs-sp__btn"
            class:bs-sp__btn--primary={!status?.upToDate}
            onclick={syncPage}
            disabled={starting || running || !status?.canSync}
          >
            {savingEditor
              ? 'Saving editor…'
              : running || starting
                ? 'Syncing…'
                : status?.upToDate
                  ? 'Re-sync anyway'
                  : inEditor
                    ? 'Save & sync this page'
                    : 'Sync this page'}
          </button>
        {/if}
      </div>
    </div>
  </div>
{/if}

<style>
  .bs-sp {
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
  .bs-sp__box {
    width: min(420px, 100%);
    max-height: 85vh;
    overflow: auto;
    background: var(--ab-color-surface, #fff);
    color: var(--ab-color-text, #0f172a);
    border-radius: 12px;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.35);
  }
  .bs-sp__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 18px;
    border-bottom: 1px solid var(--ab-color-border, #e5e7eb);
  }
  .bs-sp__title {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
  }
  .bs-sp__x {
    border: 0;
    background: none;
    font-size: 22px;
    line-height: 1;
    color: var(--ab-color-text-muted, #64748b);
    cursor: pointer;
  }
  .bs-sp__body {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 18px;
  }
  .bs-sp__page {
    margin: 0;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 13px;
    word-break: break-all;
    color: var(--ab-color-text, #0f172a);
  }
  .bs-sp__include {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    font-weight: 500;
    color: var(--ab-color-text, #0f172a);
    cursor: pointer;
  }
  .bs-sp__include input:disabled {
    cursor: not-allowed;
  }
  .bs-sp__inccount {
    margin: 0;
    font-size: 12px;
    color: var(--ab-color-text-muted, #64748b);
  }
  .bs-sp__links {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 10px 12px;
    border-radius: 8px;
    background: color-mix(in srgb, var(--ab-color-primary, #2563eb) 8%, var(--ab-color-surface, #fff));
    border: 1px solid color-mix(in srgb, var(--ab-color-primary, #2563eb) 30%, var(--ab-color-surface, #fff));
  }
  .bs-sp__links--in {
    background: color-mix(in srgb, var(--ab-color-warning, #f59e0b) 12%, var(--ab-color-surface, #fff));
    border-color: color-mix(in srgb, var(--ab-color-warning, #f59e0b) 35%, var(--ab-color-surface, #fff));
  }
  .bs-sp__linklist {
    margin: 0;
    padding-left: 18px;
    font-size: 13px;
    color: var(--ab-color-text, #0f172a);
    max-height: 9rem;
    overflow: auto;
  }
  .bs-sp__linkbtn {
    align-self: flex-start;
  }
  .bs-sp__notefoot {
    margin: 0;
    font-size: 11px;
    color: var(--ab-color-text-muted, #64748b);
  }
  .bs-sp__muted {
    margin: 0;
    font-size: 13px;
    color: var(--ab-color-text-muted, #64748b);
  }
  .bs-sp__msg {
    margin: 0;
    font-size: 13px;
  }
  .bs-sp__note {
    margin: 0;
    padding: 10px 12px;
    border-radius: 8px;
    background: color-mix(in srgb, var(--ab-color-warning, #f59e0b) 12%, var(--ab-color-surface, #fff));
    border: 1px solid color-mix(in srgb, var(--ab-color-warning, #f59e0b) 35%, var(--ab-color-surface, #fff));
    font-size: 13px;
    color: var(--ab-color-text, #92400e);
  }
  .bs-sp__msg--err {
    color: var(--ab-color-danger, #b91c1c);
  }
  .bs-sp__note--ok {
    background: color-mix(in srgb, var(--ab-color-success, #16a34a) 12%, var(--ab-color-surface, #fff));
    border-color: color-mix(in srgb, var(--ab-color-success, #16a34a) 35%, var(--ab-color-surface, #fff));
    color: var(--ab-color-text, #0f172a);
  }
  .bs-sp__targets {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 6px;
  }
  .bs-sp__target {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
  }
  .bs-sp__tname {
    flex: 1;
    font-weight: 500;
  }
  .bs-sp__tstate {
    color: var(--ab-color-text-muted, #64748b);
    font-size: 12px;
  }
  .bs-sp__dot {
    width: 9px;
    height: 9px;
    border-radius: 999px;
    background: var(--ab-color-border, #cbd5e1);
  }
  .bs-sp__dot--on {
    background: var(--ab-color-success, #16a34a);
  }
  .bs-sp__dot--off {
    background: var(--ab-color-warning, #f59e0b);
  }
  .bs-sp__progress {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 10px 12px;
    border-radius: 8px;
    background: color-mix(in srgb, var(--ab-color-text, #0f172a) 5%, var(--ab-color-surface, #fff));
  }
  .bs-sp__progress--ok {
    background: color-mix(in srgb, var(--ab-color-success, #16a34a) 12%, var(--ab-color-surface, #fff));
  }
  .bs-sp__progress--warn {
    background: color-mix(in srgb, var(--ab-color-danger, #b91c1c) 10%, var(--ab-color-surface, #fff));
  }
  .bs-sp__prow {
    display: flex;
    align-items: center;
    justify-content: space-between;
  }
  .bs-sp__phase {
    font-size: 13px;
    font-weight: 600;
    text-transform: capitalize;
  }
  .bs-sp__spin {
    animation: bs-sp-spin 0.9s linear infinite;
    color: var(--ab-color-primary, #2563eb);
  }
  .bs-sp__bar {
    height: 6px;
    border-radius: 999px;
    background: var(--ab-color-border, #e2e8f0);
    overflow: hidden;
  }
  .bs-sp__fill {
    height: 100%;
    background: var(--ab-color-primary, #2563eb);
    transition: width 0.2s ease;
  }
  .bs-sp__counts {
    margin: 0;
    font-size: 12px;
    color: var(--ab-color-text-muted, #64748b);
  }
  .bs-sp__notice {
    margin: 0;
    padding: 10px 12px;
    border-radius: 8px;
    background: color-mix(in srgb, var(--ab-color-warning, #f59e0b) 10%, var(--ab-color-surface, #fff));
    border: 1px solid color-mix(in srgb, var(--ab-color-warning, #f59e0b) 35%, var(--ab-color-surface, #fff));
    font-size: 12px;
    line-height: 1.5;
    color: var(--ab-color-text, #0f172a);
  }
  .bs-sp__foot {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
    padding: 14px 18px;
    border-top: 1px solid var(--ab-color-border, #e5e7eb);
  }
  .bs-sp__done {
    margin-right: auto;
    font-size: 13px;
    font-weight: 600;
    color: var(--ab-color-success, #16a34a);
  }
  .bs-sp__btn {
    padding: 8px 14px;
    border: 1px solid var(--ab-color-border, #cbd5e1);
    border-radius: 8px;
    background: var(--ab-color-surface, #fff);
    color: var(--ab-color-text, #0f172a);
    font: inherit;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
  }
  .bs-sp__btn--primary {
    background: var(--ab-color-primary, #2563eb);
    border-color: var(--ab-color-primary, #2563eb);
    color: var(--ab-color-primary-on, #fff);
  }
  .bs-sp__btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }

  @keyframes bs-sp-spin {
    to {
      transform: rotate(360deg);
    }
  }
</style>
