import { onBeforeUnmount, onMounted, ref } from 'vue';

declare global {
    interface Window {
        grecaptcha?: {
            execute: (
                siteKey: string,
                options: { action: string },
            ) => Promise<string>;
            ready: (callback: () => void) => void;
        };
    }
}

let scriptPromise: Promise<void> | null = null;

function loadRecaptchaScript(siteKey: string): Promise<void> {
    if (window.grecaptcha) {
        return Promise.resolve();
    }

    if (scriptPromise) {
        return scriptPromise;
    }

    scriptPromise = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = `https://www.google.com/recaptcha/api.js?render=${encodeURIComponent(siteKey)}`;
        script.async = true;
        script.defer = true;
        script.onload = () => resolve();
        script.onerror = () => reject(new Error('Failed to load reCAPTCHA.'));
        document.head.appendChild(script);
    });

    return scriptPromise;
}

export function useRecaptchaV3(siteKey: () => string | null) {
    const isReady = ref(false);

    onMounted(async () => {
        const key = siteKey();

        if (! key) {
            return;
        }

        try {
            await loadRecaptchaScript(key);
            isReady.value = true;
        } catch {
            isReady.value = false;
        }
    });

    onBeforeUnmount(() => {
        scriptPromise = null;
    });

    async function execute(action: string): Promise<string> {
        const key = siteKey();

        if (! key) {
            throw new Error('reCAPTCHA site key is not configured.');
        }

        await loadRecaptchaScript(key);

        return new Promise((resolve, reject) => {
            if (! window.grecaptcha) {
                reject(new Error('reCAPTCHA is not available.'));

                return;
            }

            window.grecaptcha.ready(async () => {
                try {
                    resolve(await window.grecaptcha!.execute(key, { action }));
                } catch (error) {
                    reject(error);
                }
            });
        });
    }

    return {
        execute,
        isReady,
    };
}
