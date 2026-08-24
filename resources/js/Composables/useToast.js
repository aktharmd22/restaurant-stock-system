import { reactive } from 'vue';

/**
 * One toast queue for the whole app. Messages are plain English and always
 * match the button that was pressed: "Send request" -> "Request sent".
 */
const state = reactive({ items: [] });
let nextId = 1;

function push({ message, type = 'info', duration = 4000, action = null }) {
    const id = nextId++;
    state.items.push({ id, message, type, action });

    if (duration > 0) {
        setTimeout(() => dismiss(id), duration);
    }

    return id;
}

function dismiss(id) {
    const index = state.items.findIndex((toast) => toast.id === id);
    if (index !== -1) state.items.splice(index, 1);
}

export function useToast() {
    return {
        toasts: state.items,
        push,
        dismiss,
        success: (message, options = {}) => push({ ...options, message, type: 'success' }),
        error: (message, options = {}) => push({ ...options, message, type: 'error' }),
        info: (message, options = {}) => push({ ...options, message, type: 'info' }),
    };
}
