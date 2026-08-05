import '@wpeasy/ab-ui/styles';
import '@wpeasy/ab-ui/styles/wp-admin.css';
import '../shared/app.css';
import { mount } from 'svelte';
import App from './App.svelte';

const target = document.getElementById('bs-dashboard');

if (target) {
  // mount() appends rather than replacing, so clear the server-rendered
  // placeholder (the "Loading…" / noscript fallback) before mounting.
  target.replaceChildren();
  mount(App, { target });
}
