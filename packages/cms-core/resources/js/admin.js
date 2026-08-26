import './bootstrap';

document.querySelectorAll('[data-cms-help-widget]').forEach((widget) => {
    const toggle = widget.querySelector('[data-cms-help-toggle]');
    const panel = widget.querySelector('[data-cms-help-panel]');
    const close = widget.querySelector('[data-cms-help-close]');

    if (! toggle || ! panel) {
        return;
    }

    const setOpen = (isOpen) => {
        panel.hidden = ! isOpen;
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    };

    toggle.addEventListener('click', () => {
        setOpen(panel.hidden);
    });

    close?.addEventListener('click', () => {
        setOpen(false);
        toggle.focus();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setOpen(false);
        }
    });

    document.addEventListener('click', (event) => {
        if (! widget.contains(event.target)) {
            setOpen(false);
        }
    });
});
