import { mount } from 'svelte';
import Docs from './Docs.svelte';

const target = document.getElementById('bs-docs');

if (target) {
  target.replaceChildren();
  mount(Docs, { target });
}
