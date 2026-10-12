/**
 * Hamburger menu for the owner navbar.
 *
 * The button is rendered by the views; this file only wires the behaviour and
 * injects a button automatically for any navbar that does not already have one,
 * so a new page cannot forget it.
 */
(function () {
    const CLOSED_CLASS = 'nav-open';

    function ensureToggle(container) {
        if (container.querySelector(':scope > .nav-toggle')) {
            return;
        }

        const actions = container.querySelector(':scope > .nav-actions, :scope > .owner-nav-actions');

        if (!actions) {
            return;
        }

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'nav-toggle';
        button.setAttribute('aria-expanded', 'false');
        button.setAttribute('aria-label', 'Buka menu');
        button.innerHTML = '<span class="nav-toggle-bar"></span>';

        container.appendChild(button);
    }

    function setState(container, open) {
        container.classList.toggle(CLOSED_CLASS, open);

        const button = container.querySelector(':scope > .nav-toggle');

        if (button) {
            button.setAttribute('aria-expanded', String(open));
            button.setAttribute('aria-label', open ? 'Tutup menu' : 'Buka menu');
        }
    }

    function init() {
        document.querySelectorAll('.navbar-container').forEach((container) => {
            ensureToggle(container);

            container.addEventListener('click', (event) => {
                const button = event.target.closest('.nav-toggle');

                if (button && container.contains(button)) {
                    setState(container, !container.classList.contains(CLOSED_CLASS));

                    return;
                }

                // Tapping a link inside the panel closes it again.
                if (event.target.closest('.nav-actions a, .owner-nav-actions a')) {
                    setState(container, false);
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', init);
})();
