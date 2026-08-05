import type {
  CheckPreview,
  ConnectionInput,
  ConnectionResponse,
  DestinationsResponse,
  DiscoveryMode,
  ExportSnapshot,
  LinkItem,
  MediaItem,
  PagesOverview,
  Preflight,
  ReplacementPage,
  VideoItem,
  ServerConfig,
  Status,
  SyncSnapshot,
  TestResult,
} from './types';

interface BsData {
  restUrl: string;
  nonce: string;
}

declare global {
  interface Window {
    bsData?: BsData;
  }
}

function config(): BsData {
  const data = window.bsData;
  if (!data) {
    throw new Error('Bricks Static: bsData was not localised.');
  }
  return data;
}

async function request<T>(path: string, method = 'GET', body?: unknown): Promise<T> {
  const { restUrl, nonce } = config();
  const res = await fetch(restUrl.replace(/\/$/, '') + path, {
    method,
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': nonce,
    },
    body: body === undefined ? undefined : JSON.stringify(body),
  });

  if (!res.ok) {
    let message = `Request failed (${res.status}).`;
    try {
      const data = (await res.json()) as { message?: string };
      if (data?.message) {
        message = data.message;
      }
    } catch {
      /* non-JSON error body */
    }
    throw new Error(message);
  }

  return (await res.json()) as T;
}

export const api = {
  getConnection: () => request<ConnectionResponse>('/connection'),
  saveConnection: (data: ConnectionInput) => request<ConnectionResponse>('/connection', 'POST', data),
  testConnection: (data: ConnectionInput) => request<TestResult>('/connection/test', 'POST', data),
  getStatus: () => request<Status>('/status'),
  getPagesOverview: () => request<PagesOverview>('/pages-overview'),
  setDiscoveryMode: (mode: DiscoveryMode) =>
    request<{ discoveryMode: DiscoveryMode }>('/settings', 'POST', { discoveryMode: mode }),
  setFabEnabled: (enabled: boolean) =>
    request<{ fabEnabled: boolean }>('/settings', 'POST', { fabEnabled: enabled }),
  setAiToggles: (toggles: { aiAllowChanges?: boolean; aiAllowSync?: boolean }) =>
    request<{ aiAllowChanges: boolean; aiAllowSync: boolean }>('/settings', 'POST', toggles),
  setConcurrentSyncs: (n: number) =>
    request<{ concurrentSyncs: number }>('/settings', 'POST', { concurrentSyncs: n }),
  setWizardSeen: () => request<{ wizardSeen: boolean }>('/settings', 'POST', { wizardSeen: true }),
  getDestinations: () => request<DestinationsResponse>('/destinations'),
  addDestination: (data: ConnectionInput) => request<DestinationsResponse>('/destinations', 'POST', data),
  updateDestination: (id: string, data: ConnectionInput) => request<DestinationsResponse>(`/destinations/${id}`, 'POST', data),
  removeDestination: (id: string) => request<DestinationsResponse>(`/destinations/${id}`, 'DELETE'),
  testDestination: (id: string, data: ConnectionInput) => request<TestResult>(`/destinations/${id}/test`, 'POST', data),
  packageTest: (id: string) => request<TestResult>(`/destinations/${id}/package-test`, 'POST', {}),
  syncStart: (type: 'check' | 'sync', opts: { prune?: boolean; dest?: string } = {}) =>
    request<SyncSnapshot>('/sync/start', 'POST', { type, ...opts }),
  checkDestination: (id: string) => request<CheckPreview>(`/sync/check?dest=${encodeURIComponent(id)}`),
  syncTick: () => request<SyncSnapshot>('/sync/tick', 'POST', {}),
  syncStatus: () => request<SyncSnapshot>('/sync'),
  deployDispatch: () => request<{ ok: boolean; spawned: number; phase: string }>('/sync/deploy-dispatch', 'POST'),
  syncCancel: () => request<SyncSnapshot>('/sync/cancel', 'POST', {}),
  syncRetry: () => request<SyncSnapshot>('/sync/retry', 'POST', {}),
  syncClaim: () => request<{ owner: 'cli' | 'browser' }>('/sync/claim', 'POST', {}),
  syncReset: () => request<{ ok: boolean }>('/sync/reset', 'POST', {}),
  preflight: () => request<Preflight>('/sync/preflight', 'POST', {}),
  serverConfig: () => request<ServerConfig>('/sync/server-config'),
  getMedia: (page = '') =>
    request<{ pages: ReplacementPage[]; page: string; media: MediaItem[] }>(
      `/media${page ? `?page=${encodeURIComponent(page)}` : ''}`,
    ),
  getLinks: () => request<{ links: LinkItem[] }>('/links'),
  getVideos: (page = '') =>
    request<{ pages: ReplacementPage[]; page: string; videos: VideoItem[] }>(
      `/videos${page ? `?page=${encodeURIComponent(page)}` : ''}`,
    ),
  exportStart: (dest: string) => request<ExportSnapshot>('/export/start', 'POST', { dest }),
  exportTick: () => request<ExportSnapshot>('/export/tick', 'POST', {}),
  exportStatus: () => request<ExportSnapshot>('/export'),
  exportCancel: () => request<ExportSnapshot>('/export/cancel', 'POST', {}),
};

/**
 * Build a one-time download URL for a finished export. Not a JSON `request()`
 * call — meant for a plain `<a href>` navigation, so it carries the `wp_rest`
 * nonce as a query param (WP core's REST cookie auth accepts `?_wpnonce=` as
 * a fallback to the `X-WP-Nonce` header a `fetch()` would normally send).
 */
export function exportDownloadUrl(token: string): string {
  const { restUrl, nonce } = config();
  return `${restUrl.replace(/\/$/, '')}/export/download?token=${encodeURIComponent(token)}&_wpnonce=${encodeURIComponent(nonce)}`;
}
