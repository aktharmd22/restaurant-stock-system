import { onBeforeUnmount, onMounted, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useSound } from '@/Composables/useSound';
import { useToast } from '@/Composables/useToast';
import { useTabBadge } from '@/Composables/useTabBadge';

/**
 * Live updates, with a fallback that actually works.
 *
 * Shared hosting cannot run Reverb, so this speaks to Pusher when credentials
 * exist and quietly polls when they do not. Either way an event does the same
 * three things: a sound, a toast, and a number in the tab title.
 */
export function useRealtime() {
    const page = usePage();
    const sound = useSound();
    const toast = useToast();
    const badge = useTabBadge();

    let echo = null;
    let pollTimer = null;
    let nagTimer = null;
    let lastUnread = page.props.alerts?.unread ?? 0;

    const config = () => page.props.realtime ?? {};
    const alerts = () => page.props.alerts ?? { unread: 0, pending: 0, latest: null };
    const user = () => page.props.auth?.user;

    function announce(payload) {
        if (!payload) return;

        sound.play(payload.sound);
        toast.push({
            message: payload.message,
            type: payload.sound === 'rejected' || payload.sound === 'failed' ? 'error' : 'info',
            duration: 8000,
            action: payload.url
                ? { label: 'Open', onClick: () => router.visit(payload.url) }
                : null,
        });
    }

    async function connectToPusher() {
        const { driver, key, cluster } = config();

        if (driver !== 'pusher' || !key) return false;

        // Loaded only when it is actually used, so the branch app does not ship
        // a websocket client it will never open.
        const [{ default: Echo }, { default: Pusher }] = await Promise.all([
            import('laravel-echo'),
            import('pusher-js'),
        ]);

        window.Pusher = Pusher;

        echo = new Echo({
            broadcaster: 'pusher',
            key,
            cluster: cluster || 'ap2',
            forceTLS: true,
        });

        const me = user();

        if (me?.is_admin_side) {
            echo.private('admin.main').listen('.request.updated', (payload) => {
                announce(payload);
                refresh();
            });
        }

        if (me?.branch?.id) {
            echo.private(`branch.${me.branch.id}`).listen('.request.updated', (payload) => {
                announce(payload);
                refresh();
            });
        }

        return true;
    }

    /** Pull the shared counters again without reloading the whole screen. */
    function refresh() {
        router.reload({ only: ['alerts', 'stats', 'requests', 'selected', 'needsAction', 'latest'] });
    }

    function startPolling() {
        const seconds = Math.max(5, config().poll_seconds ?? 12);

        pollTimer = setInterval(() => {
            // No point polling a screen nobody is looking at.
            if (document.visibilityState === 'visible') refresh();
        }, seconds * 1000);
    }

    /**
     * The single feature that stops requests being missed: while anything is
     * still sitting unopened, say so again every few minutes.
     */
    function startNagging() {
        const minutes = 5;

        nagTimer = setInterval(() => {
            if (!user()?.is_admin_side) return;
            if ((alerts().pending ?? 0) <= 0) return;
            if (document.visibilityState !== 'visible') return;

            sound.play('new_request');
        }, minutes * 60 * 1000);
    }

    onMounted(async () => {
        const me = user();
        if (!me) return;

        sound.configure({ enabled: me.sound_enabled, volume: me.sound_volume });
        sound.listenForFirstGesture();

        badge.set(alerts().pending ?? 0);

        const live = await connectToPusher();
        if (!live) startPolling();

        startNagging();
    });

    // When polling brings back a higher unread count, something happened while
    // we were not looking - announce the newest one.
    watch(
        () => alerts().unread,
        (unread) => {
            if (unread > lastUnread) announce(alerts().latest);
            lastUnread = unread;
        },
    );

    watch(
        () => alerts().pending,
        (pending) => badge.set(pending ?? 0),
    );

    // Keep the sound settings in step if the user changes them.
    watch(
        () => [user()?.sound_enabled, user()?.sound_volume],
        ([enabled, volume]) => sound.configure({ enabled, volume }),
    );

    onBeforeUnmount(() => {
        clearInterval(pollTimer);
        clearInterval(nagTimer);
        echo?.disconnect();
    });

    return { soundState: sound.state, play: sound.play };
}
