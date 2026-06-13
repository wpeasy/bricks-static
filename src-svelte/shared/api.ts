import type {
  ConnectionInput,
  ConnectionResponse,
  DestinationsResponse,
  DiscoveryMode,
  MediaItem,
  Preflight,
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
  setDiscoveryMode: (mode: DiscoveryMode) =>
    request<{ discoveryMode: DiscoveryMode }>('/settings', 'POST', { discoveryMode: mode }),
  getDestinations: () => request<DestinationsResponse>('/destinations'),
  addDestination: (data: ConnectionInput) => request<DestinationsResponse>('/destinations', 'POST', data),
  updateDestination: (id: string, data: ConnectionInput) => request<DestinationsResponse>(`/destinations/${id}`, 'POST', data),
  removeDestination: (id: string) => request<DestinationsResponse>(`/destinations/${id}`, 'DELETE'),
  testDestination: (id: string, data: ConnectionInput) => request<TestResult>(`/destinations/${id}/test`, 'POST', data),
  packageTest: (id: string) => request<TestResult>(`/destinations/${id}/package-test`, 'POST', {}),
  syncStart: (type: 'check' | 'sync', opts: { prune?: boolean; dest?: string } = {}) =>
    request<SyncSnapshot>('/sync/start', 'POST', { type, ...opts }),
  syncTick: () => request<SyncSnapshot>('/sync/tick', 'POST', {}),
  syncStatus: () => request<SyncSnapshot>('/sync'),
  syncCancel: () => request<SyncSnapshot>('/sync/cancel', 'POST', {}),
  syncRetry: () => request<SyncSnapshot>('/sync/retry', 'POST', {}),
  syncClaim: () => request<{ owner: 'cli' | 'browser' }>('/sync/claim', 'POST', {}),
  syncReset: () => request<{ ok: boolean }>('/sync/reset', 'POST', {}),
  preflight: () => request<Preflight>('/sync/preflight', 'POST', {}),
  serverConfig: () => request<ServerConfig>('/sync/server-config'),
  getMedia: () => request<{ media: MediaItem[] }>('/media'),
};
