let deferredInstallPrompt = null;
let updateDismissed = false;
let updateRequested = false;

const isStandalone = () => window.matchMedia('(display-mode: standalone)').matches
    || window.navigator.standalone === true;

const isIos = () => /iphone|ipad|ipod/i.test(window.navigator.userAgent)
    || (window.navigator.platform === 'MacIntel' && window.navigator.maxTouchPoints > 1);

const installButtons = () => document.querySelectorAll('[data-pwa-install]');

const show = (element) => element?.removeAttribute('hidden');
const hide = (element) => element?.setAttribute('hidden', '');

const syncInstallState = () => {
    const canInstall = deferredInstallPrompt !== null || (isIos() && !isStandalone());

    installButtons().forEach((button) => {
        if (canInstall && !isStandalone()) {
            show(button);
        } else {
            hide(button);
        }
    });
};

const showUpdateBanner = () => {
    if (updateDismissed || updateRequested) {
        return;
    }

    document.querySelectorAll('[data-pwa-update-banner]').forEach(show);
};

const syncUpdateState = (registration) => {
    if (registration?.waiting && navigator.serviceWorker.controller) {
        showUpdateBanner();
    }
};

const registerServiceWorker = async () => {
    if (!('serviceWorker' in navigator)) {
        return;
    }

    try {
        const registration = await navigator.serviceWorker.register('/sw.js', {
            scope: '/',
            updateViaCache: 'none',
        });

        syncUpdateState(registration);

        registration.addEventListener('updatefound', () => {
            const worker = registration.installing;

            worker?.addEventListener('statechange', () => {
                if (worker.state === 'installed' && navigator.serviceWorker.controller) {
                    showUpdateBanner();
                }
            });
        });

        navigator.serviceWorker.addEventListener('controllerchange', () => {
            if (updateRequested) {
                window.location.reload();
            }
        });
    } catch {
    }
};

const syncConnectionState = () => {
    document.querySelectorAll('[data-pwa-connection]').forEach((element) => {
        if (navigator.onLine) {
            hide(element);
        } else {
            show(element);
        }
    });
};

const requestUpdate = () => {
    updateRequested = true;
    updateDismissed = true;

    document.querySelectorAll('[data-pwa-update-banner]').forEach(hide);

    navigator.serviceWorker.getRegistration().then((registration) => {
        registration?.waiting?.postMessage({ type: 'SKIP_WAITING' });
    });
};

const install = async () => {
    if (deferredInstallPrompt === null) {
        if (isIos()) {
            document.querySelectorAll('[data-pwa-ios-instructions]').forEach(show);
        }

        return;
    }

    const prompt = deferredInstallPrompt;
    deferredInstallPrompt = null;

    await prompt.prompt();
    await prompt.userChoice;
    syncInstallState();
};

const applyPwaState = () => {
    syncInstallState();
    syncConnectionState();
    document.querySelectorAll('[data-pwa-install]').forEach((button) => {
        if (isIos() && !isStandalone()) {
            show(button);
        }
    });
};

window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    deferredInstallPrompt = event;
    syncInstallState();
});

window.addEventListener('appinstalled', () => {
    deferredInstallPrompt = null;
    syncInstallState();
});

window.addEventListener('online', syncConnectionState);
window.addEventListener('offline', syncConnectionState);
window.addEventListener('pageshow', applyPwaState);
document.addEventListener('livewire:navigated', applyPwaState);

document.addEventListener('click', (event) => {
    const installButton = event.target.closest('[data-pwa-install]');

    if (installButton) {
        event.preventDefault();
        install();

        return;
    }

    if (event.target.closest('[data-pwa-update]')) {
        event.preventDefault();
        requestUpdate();

        return;
    }

    if (event.target.closest('[data-pwa-dismiss-update]')) {
        event.preventDefault();
        updateDismissed = true;
        document.querySelectorAll('[data-pwa-update-banner]').forEach(hide);
    }
});

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', applyPwaState);
} else {
    applyPwaState();
}

window.addEventListener('load', registerServiceWorker);
