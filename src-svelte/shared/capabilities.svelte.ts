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
  maxPages: number;
  advancedReplacements: boolean;
  gzip: boolean;
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
  maxDestinations: 1,
  maxPages: 10,
  advancedReplacements: false,
  gzip: false,
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
    maxPages: Math.max(1, Number(r.maxPages) || 10),
    advancedReplacements: !!r.advancedReplacements,
    gzip: !!r.gzip,
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
