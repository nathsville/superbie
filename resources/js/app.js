import Alpine from 'alpinejs';
import { initNavigation } from './navigation.js';

window.Alpine = Alpine;
Alpine.start();

// Progressive GET navigation + in-memory page cache (Prompt 26).
// Enhancement only: if this fails, the app still works as a normal
// server-rendered Blade application.
initNavigation();
