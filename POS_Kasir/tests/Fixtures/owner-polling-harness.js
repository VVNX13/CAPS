/**
 * Runs the real public/assets/js/owner.js polling logic against a JSON payload
 * captured from the real /api/orders/check-new endpoint, and reports what the
 * dashboard actually did.
 *
 * Usage: node owner-polling-harness.js <owner.js path> <payload json path>
 * Output: a single JSON object on stdout describing the observed side effects.
 */

const fs = require('fs');
const vm = require('vm');

const [, , ownerJsPath, payloadPath] = process.argv;

if (!ownerJsPath || !payloadPath) {
    console.error('usage: node owner-polling-harness.js <owner.js> <payload.json>');
    process.exit(2);
}

const payload = JSON.parse(fs.readFileSync(payloadPath, 'utf8'));
const source = fs.readFileSync(ownerJsPath, 'utf8');

const observed = {
    loadDashboardCalls: 0,
    toastMessages: [],
    beepCalls: 0,
    lastSeenOrderId: null,
    fetchUrls: [],
};

// Minimal DOM/browser surface: owner.js only touches these inside functions.
const fakeElement = {
    innerText: '',
    innerHTML: '',
    className: '',
    dataset: {},
    classList: { toggle() {}, add() {}, remove() {} },
    addEventListener() {},
    appendChild() {},
    remove() {},
};

const sandbox = {
    console,
    Intl,
    setTimeout,
    clearTimeout,
    setInterval: () => 0,
    clearInterval: () => {},
    document: {
        getElementById: () => fakeElement,
        querySelectorAll: () => [],
        createElement: () => ({ ...fakeElement, className: '', style: {} }),
        body: { appendChild() {} },
    },
    window: { AudioContext: null, webkitAudioContext: null },
    alert() {},
    confirm: () => true,
    fetch: async (url) => {
        observed.fetchUrls.push(String(url));
        return {
            ok: true,
            status: 200,
            json: async () => payload,
        };
    },
};

sandbox.globalThis = sandbox;
vm.createContext(sandbox);

// Classic script: function declarations land on the context global, so the
// internal call to loadDashboardData() inside checkNewOrdersPolling resolves
// through the same property we can replace below.
vm.runInContext(source, sandbox, { filename: 'owner.js' });

// Replace the collaborators with recorders AFTER load, so we observe the real
// control flow of the real function.
sandbox.loadDashboardData = async () => {
    observed.loadDashboardCalls += 1;
};

sandbox.playBeepSound = () => {
    observed.beepCalls += 1;
};

sandbox.showToast = (msg) => {
    observed.toastMessages.push(String(msg));
};

(async () => {
    await sandbox.checkNewOrdersPolling('makanan,minuman');

    // lastSeenOrderId is a top-level `let`, so it is not on the sandbox global.
    // Read it back by evaluating a tiny probe in the same context.
    observed.lastSeenOrderId = vm.runInContext('lastSeenOrderId', sandbox);

    process.stdout.write(JSON.stringify(observed));
})().catch((error) => {
    process.stderr.write(String(error && error.stack ? error.stack : error));
    process.exit(1);
});
