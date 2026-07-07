<script lang="ts">
  import { onMount, untrack } from 'svelte';
  import { Button, Status } from '@wpeasy/ab-ui';
  import { __, __f } from '../shared/i18n';
  import IncludeToggle from '../lib/IncludeToggle.svelte';
  import { outboundExcluded, inboundIncluded, includeBulk, type LinkPage } from '../shared/linkCheck';

  let {
    restUrl,
    nonce,
    postId,
    pageUrl,
    published,
    included: initialIncluded,
    effective = false,
    includedCount: initialCount = 0,
    savedCount: initialSaved = 0,
    maxPages = 0,
    unlimited = false,
  }: {
    restUrl: string;
    nonce: string;
    postId: number;
    pageUrl: string;
    published: boolean;
    included: boolean;
    effective?: boolean;
    includedCount?: number;
    savedCount?: number;
    maxPages?: number;
    unlimited?: boolean;
  } = $props();

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
    counts?: { pagesDone?: number; uploaded?: number };
    totals?: { pages?: number; uploads?: number };
  }

  let included = $state(untrack(() => initialIncluded));
  let count = $state(untrack(() => initialCount));
  let savedTotal = $state(untrack(() => initialSaved));
  let savingInclude = $state(false);

  let linkPrompt = $state<{ kind: 'out' | 'in'; pages: LinkPage[] } | null>(null);
  let linkBusy = $state(false);
  let linkSkipped = $state(0);

  // Free plan: can't include another page once the cap is hit (excluding is fine).
  let atLimit = $derived(!unlimited && !included && count >= maxPages);
  // This page is saved-included but over the Free cap, so it won't export.
  let isOverLimit = $derived(included && !effective && !unlimited);

  let status = $state<PageStatus | null>(null);
  let statusErr = $state('');
  let snap = $state<Snap | null>(null);
  let starting = $state(false);

  let running = $derived(!!snap?.running);

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

  async function saveInclude(value: boolean): Promise<void> {
    const previous = included;
    included = value; // optimistic
    savingInclude = true;
    try {
      const r = await api('/editor/include', { method: 'POST', body: JSON.stringify({ postId, include: value }) });
      included = !!r.included;
      if (typeof r.includedCount === 'number') count = r.includedCount;
      if (typeof r.savedCount === 'number') savedTotal = r.savedCount;
      void runLinkCheck();
    } catch {
      included = previous; // revert on failure (e.g. at the Free limit)
    } finally {
      savingInclude = false;
    }
  }

  async function runLinkCheck(): Promise<void> {
    linkSkipped = 0;
    try {
      if (included) {
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
      if (typeof r.includedCount === 'number') count = r.includedCount;
      if (typeof r.savedCount === 'number') savedTotal = r.savedCount;
      linkSkipped = r.skipped?.length ?? 0;
      linkPrompt = null;
    } catch {
      /* ignore */
    } finally {
      linkBusy = false;
    }
  }

  async function loadStatus(): Promise<void> {
    if (!published || !pageUrl) return;
    try {
      status = await api('/sync/page-status?url=' + encodeURIComponent(pageUrl));
    } catch (e) {
      statusErr = (e as Error).message;
    }
  }

  async function syncPage(): Promise<void> {
    starting = true;
    snap = null;
    try {
      const started: Snap = await api('/sync/page', { method: 'POST', body: JSON.stringify({ url: pageUrl }) });
      snap = started;
      if (started.phase === 'error') return;

      const claim = await api('/sync/claim', { method: 'POST' });
      const browserDrives = claim?.owner === 'browser';

      while (snap?.running) {
        snap = browserDrives ? await api('/sync/tick', { method: 'POST' }) : (await sleep(1200), await api('/sync'));
      }
      await loadStatus();
    } catch (e) {
      snap = { phase: 'error', message: (e as Error).message };
    } finally {
      starting = false;
    }
  }

  onMount(loadStatus);
</script>

<div class="ab-ui bs-em bs-stack bs-stack--sm">
  <IncludeToggle
    bind:included
    label={__('editorInclude')}
    hint={__('editorIncludeHint')}
    disabled={savingInclude || atLimit}
    onChange={saveInclude}
  />

  <p class="bs-em__count">
    {unlimited ? __f('editorIncludedUnlimited', count) : __f('editorIncludedCount', count, maxPages)}
  </p>
  {#if !unlimited && savedTotal > count}
    <p class="bs-em__count">{__f('editorSavedOver', savedTotal - count)}</p>
  {/if}
  {#if isOverLimit}
    <p class="bs-em__note bs-em__note--err">{__('editorOverLimit')}</p>
  {:else if atLimit}
    <p class="bs-em__note bs-em__note--err">{__('editorLimitHint')}</p>
  {/if}

  {#if linkPrompt}
    <div class="bs-em__links bs-em__links--{linkPrompt.kind}">
      {#if linkPrompt.kind === 'out'}
        <p class="bs-em__note">{__f('editorLinksOut', linkPrompt.pages.length)}</p>
      {:else}
        <p class="bs-em__note bs-em__note--err">{__f('editorLinksIn', linkPrompt.pages.length)}</p>
      {/if}
      <ul class="bs-em__linklist">
        {#each linkPrompt.pages as p (p.postId)}<li>{p.title}</li>{/each}
      </ul>
      {#if linkPrompt.kind === 'out'}
        <Button class="bs-em__linkbtn" variant="secondary" size="sm" onclick={includeAll} disabled={linkBusy}>
          {linkBusy ? __('editorIncluding') : __('editorIncludeAll')}
        </Button>
      {:else}
        <p class="bs-em__notefoot">{__('editorLinksInNote')}</p>
      {/if}
    </div>
  {/if}
  {#if linkSkipped > 0}
    <p class="bs-em__note bs-em__note--err">{__f('editorLinkSkipped', linkSkipped)}</p>
  {/if}

  {#if !published}
    <p class="bs-em__note">{__('editorPublishFirst')}</p>
  {:else}
    {#if statusErr}
      <p class="bs-em__note bs-em__note--err">{statusErr}</p>
    {:else if status && !status.canSync}
      <p class="bs-em__note">{__('editorNoTargets')}</p>
    {:else if status}
      <ul class="bs-em__targets">
        {#each status.targets as t (t.id)}
          <li class="bs-em__target">
            <Status status={t.pushed ? 'ok' : 'warn'} label={t.name} />
            <span class="bs-em__tstate">{t.pushed ? __('stPushed') : __('editorNotPushed')}</span>
          </li>
        {/each}
      </ul>
    {/if}

    {#if snap && snap.phase !== 'idle'}
      <p class="bs-em__phase">{snap.message || snap.phase}</p>
    {/if}

    <Button
      variant="primary"
      onclick={syncPage}
      disabled={starting || running || (status !== null && !status.canSync)}
    >
      {starting || running ? __('editorSyncing') : __('editorSyncThisPage')}
    </Button>
  {/if}
</div>

<style>
  .bs-em {
    font-size: var(--ab-text-sm);
    color: var(--ab-color-text);
  }

  .bs-em__note {
    margin: 0;
    font-size: var(--ab-text-sm);
    color: var(--ab-color-text-muted);
  }

  .bs-em__note--err {
    color: var(--ab-color-danger);
  }

  .bs-em__count {
    margin: 0;
    font-size: var(--ab-text-xs);
    color: var(--ab-color-text-muted);
  }

  .bs-em__links {
    display: flex;
    flex-direction: column;
    gap: var(--ab-space-2);
    padding: var(--ab-space-3);
    border: 1px solid var(--ab-color-border);
    border-radius: var(--ab-radius-md);
    background: var(--ab-color-surface);
  }

  .bs-em__links--in {
    border-color: var(--ab-color-warning);
    background: color-mix(in srgb, var(--ab-color-warning) 7%, var(--ab-color-surface));
  }

  .bs-em__linklist {
    margin: 0;
    padding-left: var(--ab-space-5);
    font-size: var(--ab-text-xs);
    color: var(--ab-color-text-muted);
    max-height: 9rem;
    overflow: auto;
  }

  .bs-em__linkbtn {
    align-self: flex-start;
  }

  .bs-em__notefoot {
    margin: 0;
    font-size: var(--ab-text-xs);
    color: var(--ab-color-text-muted);
  }

  .bs-em__targets {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: var(--ab-space-1);
  }

  .bs-em__target {
    display: flex;
    align-items: center;
    gap: var(--ab-space-2);
  }

  .bs-em__tstate {
    margin-left: auto;
    font-size: var(--ab-text-xs);
    color: var(--ab-color-text-muted);
  }

  .bs-em__phase {
    margin: 0;
    font-size: var(--ab-text-xs);
    color: var(--ab-color-text-muted);
  }
</style>
