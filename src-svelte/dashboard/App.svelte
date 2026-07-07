<script lang="ts">
  import { onMount } from 'svelte';
  import { api } from '../shared/api';
  import { caps } from '../shared/capabilities.svelte';
  import { PURCHASE_URL } from '../shared/upsell';
  import { __, __f } from '../shared/i18n';
  import type { CheckPreview, DestinationsResponse, DiscoveryMode, Status, SyncSnapshot } from '../shared/types';
  import NoticePanel from './NoticePanel.svelte';
  import DiscoveryToggle from './DiscoveryToggle.svelte';
  import MethodPanel from './MethodPanel.svelte';
  import DestinationTabs from './DestinationTabs.svelte';
  import DestinationPanel from './DestinationPanel.svelte';
  import DestinationToolbar from './DestinationToolbar.svelte';
  import ReplacementsPanel from './ReplacementsPanel.svelte';
  import ManualRunBanner from './ManualRunBanner.svelte';
  import AllDestinationsPanel from './AllDestinationsPanel.svelte';
  import ProgressPanel from './ProgressPanel.svelte';
  import ServerConfigPanel from './ServerConfigPanel.svelte';
  import SettingsDrawer from './SettingsDrawer.svelte';
  import { uiPrefs, themeAttr, accentAttr } from '../shared/uiPrefs.svelte';
  import { Badge, Button, ConfirmButton, Tabs, Tooltip, fadeUp, autoContrast } from '@wpeasy/ab-ui';

  const freeVersion = (window as unknown as { bsData?: { version?: string } }).bsData?.version ?? '';

  let dests = $state<DestinationsResponse | null>(null);
  let status = $state<Status | null>(null);
  let sync = $state<SyncSnapshot | null>(null);
  let syncing = $state(false);
  // Synchronous (no-render) Check result for the active destination.
  let checkResult = $state<CheckPreview | null>(null);
  let checking = $state(false);
  let manualRun = $state(false);
  let runCommand = $state('');
  let topTab = $state('destinations');
  let activeTab = $state('');
  let loadError = $state('');
  let settingsOpen = $state(false);

  let destinations = $derived(dests?.destinations ?? []);
  let activeDest = $derived(destinations.find((d) => d.id === activeTab) ?? null);

  const sleep = (ms: number): Promise<void> => new Promise((r) => setTimeout(r, ms));

  async function loadDestinations(): Promise<void> {
    try {
      dests = await api.getDestinations();
      if (!activeTab || !destinations.some((d) => d.id === activeTab) && activeTab !== 'all') {
        activeTab = destinations.length > 1 ? 'all' : (destinations[0]?.id ?? '');
      }
    } catch (e) {
      loadError = (e as Error).message;
    }
  }

  async function loadStatus(): Promise<void> {
    try {
      status = await api.getStatus();
    } catch (e) {
      loadError = (e as Error).message;
    }
  }

  // Grace period for a spawned WP-CLI process to show a heartbeat before we
  // conclude the auto-spawn didn't launch and ask the user to run it by hand.
  const CLI_SPAWN_GRACE_MS = 8000;

  async function runActiveJob(): Promise<void> {
    if (syncing) return;
    syncing = true;
    manualRun = false;
    try {
      // The browser only drives the run itself when there is NO WP-CLI to do it
      // (driver === 'browser'). A /sync/tick web request holds a PHP worker AND
      // fires a loopback render needing a second worker — on hosts with very few
      // workers (Local on Windows runs just two php-cgi processes) the loopback
      // can route back to the busy worker and deadlock. So when WP-CLI IS present
      // (driver === 'cli') we never tick: we poll for the CLI process, and if its
      // auto-spawn never starts we ask the user to run the command instead — then
      // pick the run back up here the moment it's going.
      let driving = false;
      if (sync?.driver !== 'cli') {
        driving = (await api.syncClaim()).owner === 'browser';
      }
      const startedAt = Date.now();
      while ((sync && sync.running) || manualRun) {
        if (driving) {
          sync = await api.syncTick();
          continue;
        }

        await sleep(manualRun ? 2500 : 1500);
        sync = await api.syncStatus();

        // Parallel deploy: the detached CLI coordinator can't spawn the worker
        // pool on Windows (no console), so it flags needsDispatch and we launch
        // the workers from here — a web request with valid handles. Idempotent
        // server-side (it also re-fires if a worker later dies).
        if (sync?.phase === 'deploy' && sync.needsDispatch) {
          try {
            await api.deployDispatch();
          } catch {
            /* transient — the next poll retries while needsDispatch stays set */
          }
        }

        if (sync?.cliAlive) {
          manualRun = false; // a CLI process is driving it (auto-spawn or user-run)
        } else if (sync?.running && Date.now() - startedAt > CLI_SPAWN_GRACE_MS) {
          manualRun = true; // auto-spawn didn't start — prompt the user to run it
        } else if (!sync?.running && !manualRun) {
          break; // finished, nothing pending
        }
      }
    } catch (e) {
      loadError = (e as Error).message;
    } finally {
      syncing = false;
      manualRun = false;
      // Refresh BOTH: the global status panel and the per-destination dots in
      // the toolbar (Connected / Pushed / In sync), which come from /destinations.
      void loadStatus();
      void loadDestinations();
    }
  }

  function dismissManualRun(): void {
    manualRun = false; // the run loop exits on its next tick if no job is active
  }

  async function setDiscoveryMode(mode: DiscoveryMode): Promise<void> {
    if (!status) return;
    const previous = status.discoveryMode;
    status.discoveryMode = mode; // optimistic
    // A mode change makes the existing render (and its page list) stale, so flip
    // the control to "Process" right away; loadStatus() below reconciles the
    // case where the user switches back to the already-rendered mode.
    status.renderCurrent = false;
    try {
      const r = await api.setDiscoveryMode(mode);
      status.discoveryMode = r.discoveryMode;
      void loadStatus();
    } catch (e) {
      status.discoveryMode = previous;
      loadError = (e as Error).message;
    }
  }

  async function setFabEnabled(enabled: boolean): Promise<void> {
    if (!status) return;
    const previous = status.fabEnabled;
    status.fabEnabled = enabled; // optimistic
    try {
      const r = await api.setFabEnabled(enabled);
      status.fabEnabled = r.fabEnabled;
    } catch (e) {
      status.fabEnabled = previous;
      loadError = (e as Error).message;
    }
  }

  async function setConcurrentSyncs(n: number): Promise<void> {
    if (!status) return;
    const previous = status.concurrentSyncs;
    status.concurrentSyncs = n; // optimistic
    try {
      const r = await api.setConcurrentSyncs(n);
      status.concurrentSyncs = r.concurrentSyncs;
    } catch (e) {
      status.concurrentSyncs = previous;
      loadError = (e as Error).message;
    }
  }

  async function setAiToggles(toggles: { aiAllowChanges?: boolean; aiAllowSync?: boolean }): Promise<void> {
    if (!status) return;
    const prev = { aiAllowChanges: status.aiAllowChanges, aiAllowSync: status.aiAllowSync };
    if (toggles.aiAllowChanges !== undefined) status.aiAllowChanges = toggles.aiAllowChanges; // optimistic
    if (toggles.aiAllowSync !== undefined) status.aiAllowSync = toggles.aiAllowSync;
    try {
      const r = await api.setAiToggles(toggles);
      status.aiAllowChanges = r.aiAllowChanges;
      status.aiAllowSync = r.aiAllowSync;
    } catch (e) {
      status.aiAllowChanges = prev.aiAllowChanges;
      status.aiAllowSync = prev.aiAllowSync;
      loadError = (e as Error).message;
    }
  }

  // The exact CLI command for this run, shown if the auto-spawn doesn't start so
  // the user can run it by hand — targeting the same destination(s) they chose.
  function buildRunCommand(type: 'check' | 'sync', dest: string, prune: boolean): string {
    let cmd = status?.cli ?? 'wp bricks-static sync';
    if (type === 'check') cmd += ' --check';
    if (dest === 'all') cmd += ' --all';
    else if (dest) cmd += ` --dest=${dest}`;
    if (prune) cmd += ' --prune';
    return cmd;
  }

  // Render the site for the current discovery mode so the included/excluded
  // page list is accurate (no destination/upload — just refreshes the manifest).
  async function processPages(): Promise<void> {
    runCommand = buildRunCommand('check', '', false);
    checkResult = null; // a fresh render invalidates the last preview
    try {
      sync = await api.syncStart('check', {});
      await runActiveJob();
    } catch (e) {
      loadError = (e as Error).message;
    }
  }

  // Check is a read-only preview now: diff the existing render against what was
  // last pushed to this destination — NO re-render (Process + content tracking
  // own the render). If the render is stale/missing the result asks to Process.
  async function startCheck(destId: string): Promise<void> {
    checking = true;
    checkResult = null;
    try {
      checkResult = await api.checkDestination(destId);
    } catch (e) {
      loadError = (e as Error).message;
    } finally {
      checking = false;
    }
  }

  // Confirmation lives in the Sync controls (ab-ui ConfirmButton), so by the time
  // we're here the user has already opted in.
  async function startSync(dest: string, prune: boolean): Promise<void> {
    runCommand = buildRunCommand('sync', dest, prune);
    checkResult = null; // the push changes what's in sync — drop the stale preview
    try {
      sync = await api.syncStart('sync', { dest, prune });
      await runActiveJob();
    } catch (e) {
      loadError = (e as Error).message;
    }
  }

  async function retryUploads(): Promise<void> {
    try {
      sync = await api.syncRetry();
      await runActiveJob();
    } catch (e) {
      loadError = (e as Error).message;
    }
  }

  async function cancelSync(): Promise<void> {
    try {
      sync = await api.syncCancel();
      // The run loop's finally normally refreshes these; when cancelling a run
      // that's no longer being driven (e.g. after a refresh) it isn't, so do it
      // here to update the global status + per-destination dots.
      void loadStatus();
      void loadDestinations();
    } catch (e) {
      loadError = (e as Error).message;
    }
  }

  async function resetSync(): Promise<void> {
    try {
      await api.syncReset();
      // Don't wipe the visible progress of the last run — just refresh the
      // status + per-destination dots to reflect the cleared push records.
      await Promise.all([loadStatus(), loadDestinations()]);
    } catch (e) {
      loadError = (e as Error).message;
    }
  }

  async function addDestination(): Promise<void> {
    try {
      dests = await api.addDestination({ name: __f('destinationNumbered', destinations.length + 1) });
      activeTab = destinations[destinations.length - 1]?.id ?? activeTab;
    } catch (e) {
      loadError = (e as Error).message;
    }
  }

  async function renameDestination(id: string, name: string): Promise<void> {
    try {
      dests = await api.updateDestination(id, { name });
    } catch (e) {
      loadError = (e as Error).message;
    }
  }

  async function removeDestination(id: string): Promise<void> {
    try {
      dests = await api.removeDestination(id);
      activeTab = destinations.length > 1 ? 'all' : (destinations[0]?.id ?? '');
    } catch (e) {
      loadError = (e as Error).message;
    }
  }

  onMount(() => {
    void (async () => {
      await loadDestinations();
      await loadStatus();
      try {
        sync = await api.syncStatus();
      } catch {
        /* ignore */
      }
    })();
  });
</script>

<div
  class="ab-ui bs-dash bs-stack bs-stack--lg"
  class:ab-compact={uiPrefs.compact}
  style="--ab-rem: 16px"
  style:--ab-primary={uiPrefs.accent === 'custom' ? uiPrefs.customPrimary : undefined}
  style:--ab-accent={uiPrefs.accent === 'custom' ? uiPrefs.customAccent : undefined}
  style:--ab-transition-distance={uiPrefs.accent === 'custom' ? `${uiPrefs.distance}px` : undefined}
  style:--ab-transition-duration={uiPrefs.accent === 'custom' ? `${uiPrefs.duration}ms` : undefined}
  data-theme={themeAttr()}
  data-accent={accentAttr()}
  use:autoContrast
>
  <div class="bs-dash__top">
    <header class="bs-stack bs-stack--xs">
      <h1>
        Bricks Static
        <Badge class={caps.edition === 'pro' ? 'bs-verbadge bs-verbadge--pro' : 'bs-verbadge'} variant="soft">
          v{freeVersion}{#if caps.proVersion} · Pro v{caps.proVersion}{/if}
        </Badge>
        <span class="bs-dash__by">{__('byPrefix')} <a href="https://brxprod.com" target="_blank" rel="noopener noreferrer">BRXProd</a></span>
      </h1>
      <p class="bs-dash__lead">{__('appLead')}</p>
    </header>
    <Tooltip content={__('settings')} placement="bottom-end">
      <Button variant="ghost" shape="square" onclick={() => (settingsOpen = true)} aria-label={__('settings')}>⚙</Button>
    </Tooltip>
  </div>

  {#if caps.edition !== 'pro'}
    <div class="bs-freebox">
      <div class="bs-freebox__head">
        <strong class="bs-freebox__title">{__('freeYouAreOn')}</strong>
        <a class="bs-freebox__btn" href={PURCHASE_URL} target="_blank" rel="noopener noreferrer">{__('upgradeToPro')}</a>
      </div>
      <div class="bs-freebox__cols">
        <div class="bs-freebox__col">
          <h3 class="bs-freebox__colhead">{__('freeThisPlugin')}</h3>
          <ul class="bs-freebox__list">
            <li>{__f('freeStaticGen', caps.maxPages)}</li>
            <li>{__('freeOneDest')}</li>
            <li>{__('freeTextRepl')}</li>
            <li>{__('freeMediaRepl')}</li>
            <li>{__('freePerFile')}</li>
            <li>{__('freeHtaccess')}</li>
            <li>{__('freeSinglePage')}</li>
          </ul>
        </div>
        <div class="bs-freebox__col bs-freebox__col--pro">
          <h3 class="bs-freebox__colhead">{__('proAddon')}</h3>
          <ul class="bs-freebox__list">
            <!-- eslint-disable svelte/no-at-html-tags -->
            <li>{@html __('proUnlimitedPages')}</li>
            <li>{@html __('proUnlimitedDests')}</li>
            <!-- eslint-enable svelte/no-at-html-tags -->
            <li>{__('proAdvRepl')}</li>
            <li>{__('proGzip')}</li>
            <li>{__('proPrune')}</li>
            <li>{__('proSitemap')}</li>
          </ul>
        </div>
      </div>
    </div>
  {/if}

  {#if loadError}<div class="bs-dash__error">{loadError}</div>{/if}

  {#if status}
    <div class="bs-globalbar">
      <DiscoveryToggle
        section="actions"
        mode={status.discoveryMode}
        disabled={syncing}
        renderCurrent={status.renderCurrent}
        excludedPublished={status.excludedPublished}
        onChange={setDiscoveryMode}
        onProcess={processPages}
      />
      <div class="bs-globalbar__notice">
        <NoticePanel isLocal={status.isLocal} cli={status.cli} wpCli={status.wpCli} inline />
      </div>
    </div>
  {/if}

  <SettingsDrawer
    bind:open={settingsOpen}
    {status}
    {syncing}
    onSetDiscoveryMode={setDiscoveryMode}
    onProcess={processPages}
    onSetFabEnabled={setFabEnabled}
    onSetAiToggles={setAiToggles}
    onSetConcurrentSyncs={setConcurrentSyncs}
  />

  <!-- Global progress for the current/last sync, above the tabs and labelled
       with its target — visible whichever tab is open. -->
  <ProgressPanel snapshot={sync} onRetry={retryUploads} retrying={syncing} />

  <Tabs
    bind:value={topTab}
    items={[
      { value: 'destinations', label: __('tabDestinations') },
      { value: 'server', label: __('tabServerConfig') },
    ]}
  >
    {#snippet panel(tab)}
      <div class="bs-stack bs-stack--lg">
        {#if tab === 'destinations'}
          {#if dests}
            <DestinationTabs {destinations} active={activeTab} onSelect={(t) => (activeTab = t)} onAdd={addDestination} onRename={renameDestination} />

            <!-- Swap between the All / per-destination panel fades in on change
                 ({#key} + in:fadeUp, the lib's content-swap transition). -->
            {#key activeTab}
              <div class="bs-stack bs-stack--lg" in:fadeUp>
                {#if activeTab === 'all'}
                  <div class="bs-panels">
                    <AllDestinationsPanel
                      {destinations}
                      running={syncing}
                      onSyncAll={(prune) => startSync('all', prune)}
                      onSyncOne={(id, prune) => startSync(id, prune)}
                      onSelect={(id) => (activeTab = id)}
                    />
                  </div>
                {:else if activeDest}
                  <DestinationToolbar
                    destination={activeDest}
                    running={syncing}
                    checking={checking}
                    result={checkResult?.destId === activeDest.id ? checkResult : null}
                    onSaved={loadDestinations}
                    onCheck={startCheck}
                    onSync={startSync}
                    onProcess={processPages}
                  />
                  <div class="bs-destrow">
                    <DestinationPanel
                      destination={activeDest}
                      capabilities={dests.capabilities}
                      running={syncing}
                      canRemove={destinations.length > 1}
                      onSaved={loadDestinations}
                      onRemove={removeDestination}
                    />
                    <ReplacementsPanel destination={activeDest} running={syncing} onSaved={loadDestinations} />
                  </div>
                {/if}
              </div>
            {/key}
          {/if}

          {#if manualRun && status}
            <ManualRunBanner command={runCommand || status.cli} onDismiss={dismissManualRun} />
          {/if}

          <div class="bs-row bs-row--between bs-controls">
            <span class="bs-controls__hint">{__('resetHint')}</span>
            <div class="bs-row">
              {#if syncing || sync?.running}
                <Button variant="ghost" size="sm" onclick={cancelSync}>{__('btnCancel')}</Button>
              {/if}
              <ConfirmButton
                variant="ghost"
                size="sm"
                label={__('btnResetSync')}
                confirmLabel={__('btnConfirmReset')}
                onconfirm={resetSync}
              />
            </div>
          </div>
        {:else}
          <div class="bs-panels">
            <MethodPanel method={status?.method ?? null} mode={status?.discoveryMode} />
            <ServerConfigPanel />
          </div>
        {/if}
      </div>
    {/snippet}
  </Tabs>
</div>

<style>
  .bs-dash {
    padding: var(--ab-space-5);
    background: var(--ab-color-bg);
    border-radius: var(--ab-radius-lg);
  }

  /* Header row: title block on the left, settings gear pinned top-right. */
  .bs-dash__top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: var(--ab-space-4);
  }


  .bs-panels {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 500px));
    gap: var(--ab-space-5);
    align-items: stretch;
  }

  /* Single-destination layout: a full-width flex row — the Connection panel is a
     fixed width and the Replacements panel fills the rest (wraps when narrow). */
  .bs-destrow {
    display: flex;
    flex-wrap: wrap;
    gap: var(--ab-space-5);
    align-items: flex-start;
  }

  .bs-dash__lead {
    color: var(--ab-color-text-muted);
    font-size: var(--ab-text-lg);
  }

  :global(.ab-ui .ab-badge.bs-verbadge) {
    margin-left: var(--ab-space-2);
    vertical-align: middle;
  }

  /* Pro accent — purple isn't an ab-ui tone, so tint the Badge directly. */
  :global(.ab-ui .ab-badge.bs-verbadge--pro) {
    background: color-mix(in srgb, #7c3aed 18%, transparent);
    border-color: #7c3aed;
    color: var(--ab-color-text);
  }

  .bs-dash__by {
    margin-left: var(--ab-space-3);
    font-size: var(--ab-text-sm);
    font-weight: var(--ab-weight-normal);
    color: var(--ab-color-text-muted);
    vertical-align: middle;
  }

  .bs-dash__by a {
    color: var(--ab-color-primary);
    text-decoration: none;
  }

  .bs-dash__by a:hover {
    text-decoration: underline;
  }

  .bs-freebox {
    display: flex;
    flex-direction: column;
    gap: var(--ab-space-4);
    padding: var(--ab-space-4) var(--ab-space-5);
    border: 1px solid #7c3aed;
    border-radius: var(--ab-radius-lg);
    background: color-mix(in srgb, #7c3aed 6%, var(--ab-color-surface));
  }

  .bs-freebox__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--ab-space-4);
    flex-wrap: wrap;
  }

  .bs-freebox__title {
    font-size: var(--ab-text-md);
  }

  .bs-freebox__cols {
    display: grid;
    grid-template-columns: 1fr;
    gap: var(--ab-space-4);
  }

  @media (min-width: 640px) {
    .bs-freebox__cols {
      grid-template-columns: 1fr 1fr;
    }
  }

  .bs-freebox__col {
    padding: var(--ab-space-3) var(--ab-space-4);
    border: 1px solid var(--ab-color-border);
    border-radius: var(--ab-radius-md);
    background: var(--ab-color-surface);
  }

  .bs-freebox__col--pro {
    border-color: #7c3aed;
    background: color-mix(in srgb, #7c3aed 8%, var(--ab-color-surface));
  }

  .bs-freebox__colhead {
    margin: 0 0 var(--ab-space-1);
    font-size: var(--ab-text-sm);
    font-weight: var(--ab-weight-semibold);
  }

  .bs-freebox__list {
    margin: 0;
    padding-left: var(--ab-space-5);
    color: var(--ab-color-text-muted);
    font-size: var(--ab-text-sm);
    line-height: 1.6;
  }

  .bs-freebox__btn {
    flex: 0 0 auto;
    padding: var(--ab-space-2) var(--ab-space-5);
    border-radius: var(--ab-radius-md);
    background: #7c3aed;
    color: #fff;
    font-weight: var(--ab-weight-semibold);
    text-decoration: none;
    white-space: nowrap;
  }

  .bs-freebox__btn:hover {
    filter: brightness(1.08);
  }

  .bs-dash__error {
    padding: var(--ab-space-3) var(--ab-space-4);
    border: 1px solid var(--ab-color-danger);
    border-radius: var(--ab-radius-md);
    color: var(--ab-color-danger);
    background: color-mix(in srgb, var(--ab-color-danger) 8%, transparent);
  }

  .bs-globalbar {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    flex-wrap: wrap;
    gap: var(--ab-space-3) var(--ab-space-5);
    padding: var(--ab-space-3) var(--ab-space-4);
    background: var(--ab-color-surface);
    border: 1px solid var(--ab-color-border);
    border-radius: var(--ab-radius-md);
  }

  .bs-globalbar__notice {
    flex: 0 1 auto;
    min-width: 0;
    margin-left: auto;
  }

  .bs-controls__hint {
    font-size: var(--ab-text-xs);
    color: var(--ab-color-text-muted);
  }
</style>
