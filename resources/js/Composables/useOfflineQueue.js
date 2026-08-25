import { reactive } from 'vue';
import { useToast } from '@/Composables/useToast';

/**
 * Kitchens have bad signal. Losing a request because someone walked into the
 * cold room is exactly the kind of thing that sends people back to WhatsApp.
 *
 * So a send that fails for network reasons is kept on the phone and sent again
 * when the connection returns. That is only safe because every queued send
 * carries a token the server recognises: if the original did get through, the
 * retry gets the same request back instead of making a second one.
 */
const STORAGE_KEY = 'pending-sends';

const state = reactive({
    online: typeof navigator === 'undefined' ? true : navigator.onLine,
    pending: 0,
    sending: false,
});

function read() {
    try {
        return JSON.parse(localStorage.getItem(STORAGE_KEY) ?? '[]');
    } catch {
        return [];
    }
}

function write(items) {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
        state.pending = items.length;
    } catch {
        // Private mode, or a full disk. Nothing useful to do here.
    }
}

function enqueue(item) {
    const items = read();
    items.push({ ...item, queuedAt: Date.now() });
    write(items);
}

/** Anything that is not a clean HTTP answer counts as "the network died". */
function isNetworkFailure(error) {
    return !error?.response;
}

async function flush() {
    if (state.sending) return;

    const items = read();
    if (!items.length) return;

    state.sending = true;
    const toast = useToast();
    const remaining = [];

    for (const item of items) {
        try {
            await window.axios.post(item.url, item.data);
        } catch (error) {
            // A real rejection from the server (a validation error, say) means
            // retrying will never work - drop it and say so, rather than
            // retrying forever in silence.
            if (isNetworkFailure(error)) {
                remaining.push(item);
            } else {
                toast.error(`${item.label} could not be sent. Please try again.`, { duration: 9000 });
            }

            continue;
        }
    }

    const sent = items.length - remaining.length;
    write(remaining);
    state.sending = false;

    if (sent > 0) {
        toast.success(sent === 1 ? 'Your request has been sent.' : `${sent} saved requests have been sent.`);
    }
}

export function useOfflineQueue() {
    if (typeof window !== 'undefined' && !window.__offlineQueueReady) {
        window.__offlineQueueReady = true;
        state.pending = read().length;

        window.addEventListener('online', () => {
            state.online = true;
            flush();
        });

        window.addEventListener('offline', () => {
            state.online = false;
        });

        if (state.online) flush();
    }

    return { state, enqueue, flush, isNetworkFailure };
}
