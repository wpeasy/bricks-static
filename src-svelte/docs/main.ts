import '@wpeasy/ab-ui/styles';
import '@wpeasy/ab-ui/styles/wp-admin.css';
import '../shared/app.css';
import { mount } from 'svelte';
import Docs from './Docs.svelte';

const target = document.getElementById('bs-docs');

if (target) {
  target.replaceChildren();
  mount(Docs, { target });
}
