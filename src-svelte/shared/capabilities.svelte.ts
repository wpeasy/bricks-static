/**
 * Edition / capability map for the dashboard — the JS mirror of PHP's
 * Support\Edition::capabilities(). Read at boot from `window.bsData.capabilities`
 * and refreshable from the live /status payload, so the UI tracks a license
 * change without a hard reload. Reactive: components reading `caps.*` re-render
 * when it updates.
 */

export interface Capabilities {
  edition: 'free' | 'pro';
  maxDestinations: number;
  /** Fixed Free-tier destination cap — edition-independent, for comparison UI. */
  freeMaxDestinations: number;
  maxPages: number;
  /** Fixed Free-tier page cap — edition-independent, for Free-vs-Pro comparison UI. */
  freeMaxPages: number;
  advancedReplacements: boolean;
  /** Media replacement is a Free feature; this is the per-page cap (Pro unlimited). */
  maxMediaPerPage: number;
  gzip: boolean;
  /** Whether Export ZIP/Sync can actually produce .gz siblings right now — the edition gate AND the runtime gzencode() gate. */
  gzipAvailable: boolean;
  sitemap: boolean;
  prune: boolean;
  licenseValid: boolean;
  /** free | unlicensed | valid | grace | expired — drives upgrade vs renew copy. */
  licenseState: string;
  /** The Pro addon's version when installed, else '' (Free only). */
  proVersion: string;
}

const FREE_DEFAULTS: Capabilities = {
  edition: 'free',
  maxDestinations: 2,
  freeMaxDestinations: 2,
  maxPages: 10,
  freeMaxPages: 10,
  advancedReplacements: false,
  maxMediaPerPage: 1,
  gzip: false,
  gzipAvailable: false,
  sitemap: false,
  prune: false,
  licenseValid: false,
  licenseState: 'free',
  proVersion: '',
};

function normalize(raw: unknown): Capabilities {
  if (!raw || typeof raw !== 'object') {
    return { ...FREE_DEFAULTS };
  }
  const r = raw as Record<string, unknown>;
  return {
    edition: r.edition === 'pro' ? 'pro' : 'free',
    maxDestinations: Math.max(1, Number(r.maxDestinations) || 1),
    freeMaxDestinations: Math.max(1, Number(r.freeMaxDestinations) || 2),
    maxPages: Math.max(1, Number(r.maxPages) || 10),
    freeMaxPages: Math.max(1, Number(r.freeMaxPages) || 10),
    advancedReplacements: !!r.advancedReplacements,
    maxMediaPerPage: Math.max(1, Number(r.maxMediaPerPage) || 1),
    gzip: !!r.gzip,
    gzipAvailable: !!r.gzipAvailable,
    sitemap: !!r.sitemap,
    prune: !!r.prune,
    licenseValid: !!r.licenseValid,
    licenseState: typeof r.licenseState === 'string' ? r.licenseState : 'free',
    proVersion: typeof r.proVersion === 'string' ? r.proVersion : '',
  };
}

function boot(): Capabilities {
  const data = (window as unknown as { bsData?: { capabilities?: unknown } }).bsData;
  return normalize(data?.capabilities);
}

/** Reactive capability map. Read `caps.advancedReplacements` etc. in components. */
export const caps = $state<Capabilities>(boot());

/** Replace the capability map from a fresh payload (e.g. the /status poll). */
export function setCaps(raw: unknown): void {
  Object.assign(caps, normalize(raw));
}

/** Whether another destination may be added under the current cap. */
export function canAddDestination(count: number): boolean {
  return count < caps.maxDestinations;
}
