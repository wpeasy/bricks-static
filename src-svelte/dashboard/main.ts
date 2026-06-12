import { mount } from 'svelte';
import App from './App.svelte';

const target = document.getElementById('bs-dashboard');

if (target) {
  mount(App, { target });
}
