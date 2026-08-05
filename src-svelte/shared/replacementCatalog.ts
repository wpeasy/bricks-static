/**
 * The catalogue of replacement panels rendered in the Replacements accordion.
 */

export type ReplacementKey = 'text' | 'media' | 'links' | 'videos';

export interface ReplacementEntry {
  key: ReplacementKey;
  title: string;
  description: string;
  /** Property on a destination holding this replacement's saved rows (for the count badge). */
  countProp: 'replacements' | 'mediaReplacements' | 'linkReplacements' | 'videoReplacements';
}

export const REPLACEMENT_CATALOG: ReplacementEntry[] = [
  {
    key: 'text',
    title: 'Text',
    countProp: 'replacements',
    description: 'Find and replace visible text on this destination only.',
  },
  {
    key: 'media',
    title: 'Images',
    countProp: 'mediaReplacements',
    description: 'Swap any of a page\'s images for another library item — responsive variants are rebuilt automatically.',
  },
  {
    key: 'links',
    title: 'Links',
    countProp: 'linkReplacements',
    description: 'Rewrite link and button targets per destination, without touching body text.',
  },
  {
    key: 'videos',
    title: 'Videos',
    countProp: 'videoReplacements',
    description: 'Swap local or embedded videos and fix embed origins for the destination domain.',
  },
];
