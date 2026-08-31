import './bootstrap';

// Mobile navigation drawer. Deliberately hand-rolled rather than pulling in
// Alpine or similar: AD-16 wants the shipped artifact small and free of
// runtime fetches, and this is the only interactive behaviour the UI needs.
const toggle = document.querySelector('[data-nav-toggle]');
const drawer = document.querySelector('[data-nav-drawer]');
const scrim = document.querySelector('[data-nav-scrim]');

if (toggle && drawer && scrim) {
    const setOpen = (open) => {
        drawer.classList.toggle('-translate-x-full', !open);
        scrim.classList.toggle('hidden', !open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        document.body.classList.toggle('overflow-hidden', open);
    };

    toggle.addEventListener('click', () => {
        setOpen(toggle.getAttribute('aria-expanded') !== 'true');
    });

    scrim.addEventListener('click', () => setOpen(false));

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setOpen(false);
        }
    });
}
