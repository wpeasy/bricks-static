import '@wpeasy/ab-ui/styles';
import '@wpeasy/ab-ui/styles/wp-admin.css';
import '../shared/app.css';
import { mount } from 'svelte';
import App from './App.svelte';
import { registerReplacementPanel } from '../shared/panelRegistry.svelte';

// Expose the replacement-panel registry on window.BS BEFORE mounting, so the
// separately-built Pro bundle (loaded after this one) can inject its panels
// without the Free bundle ever importing a Pro component.
const w = window as unknown as {
  BS?: { registerReplacementPanel?: typeof registerReplacementPanel };
};
w.BS = w.BS || {};
w.BS.registerReplacementPanel = registerReplacementPanel;

const target = document.getElementById('bs-dashboard');

if (target) {
  // mount() appends rather than replacing, so clear the server-rendered
  // placeholder (the "Loading…" / noscript fallback) before mounting.
  target.replaceChildren();
  mount(App, { target });
}
