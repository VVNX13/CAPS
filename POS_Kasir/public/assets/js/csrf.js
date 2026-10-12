/**
 * Attaches the Laravel CSRF token to every mutating fetch() call and asks for a
 * JSON response on API calls, so the existing front-end code can stay untouched.
 */
(function () {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    if (!token) {
        return;
    }

    const originalFetch = window.fetch.bind(window);

    window.fetch = function (input, init) {
        const options = init || {};
        const url = typeof input === 'string' ? input : (input && input.url) || '';
        const method = (options.method || (input && input.method) || 'GET').toUpperCase();

        const headers = new Headers(options.headers || (input && input.headers) || {});

        if (method !== 'GET' && method !== 'HEAD') {
            headers.set('X-CSRF-TOKEN', token);
        }

        if (url.indexOf('/api/') !== -1) {
            headers.set('Accept', 'application/json');
        }

        return originalFetch(input, Object.assign({}, options, { headers: headers }));
    };
})();
