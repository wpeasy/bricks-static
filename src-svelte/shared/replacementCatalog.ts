/**
 * The full catalogue of replacement panels, defined in Free so the dashboard can
 * always render a row for every feature — functional for Free tiers, and a
 * disabled "Requires Pro" teaser for Pro tiers when unlicensed. The Pro addon
 * supplies the functional panels for the `pro` entries at runtime via the panel
 * registry; it does not redefine the catalogue.
 */

export type ReplacementKey = 'text' | 'media' | 'links' | 'videos';
export type ReplacementTier = 'free' | 'pro';

export interface ReplacementEntry {
  key: ReplacementKey;
  title: string;
  description: string;
  tier: ReplacementTier;
  /** Property on a destination holding this replacement's saved rows (for the count badge). */
  countProp: 'replacements' | 'mediaReplacements' | 'linkReplacements' | 'videoReplacements';
}

export const REPLACEMENT_CATALOG: ReplacementEntry[] = [
  {
    key: 'text',
    title: 'Text',
    tier: 'free',
    countProp: 'replacements',
    description: 'Find and replace visible text on this destination only.',
  },
  {
    key: 'media',
    title: 'Media',
    tier: 'pro',
    countProp: 'mediaReplacements',
    description: 'Swap images and media for other library items per destination — responsive variants are rebuilt automatically.',
  },
  {
    key: 'links',
    title: 'Links',
    tier: 'pro',
    countProp: 'linkReplacements',
    description: 'Rewrite link and button targets per destination, without touching body text.',
  },
  {
    key: 'videos',
    title: 'Videos',
    tier: 'pro',
    countProp: 'videoReplacements',
    description: 'Swap local or embedded videos and fix embed origins for the destination domain.',
  },
];
