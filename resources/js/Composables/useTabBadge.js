/**
 * The count in the browser tab, so a background tab still says there is work.
 * The favicon gets a dot for the same reason.
 */
let baseTitle = null;

function ensureBaseTitle() {
    if (baseTitle === null) {
        baseTitle = document.title.replace(/^\(\d+\)\s*/, '');
    }

    return baseTitle;
}

function paintFavicon(count) {
    const link = document.querySelector("link[rel='icon']");
    if (!link) return;

    if (count <= 0) {
        link.href = '/favicon.svg';
        return;
    }

    // Drawn rather than fetched, so it works offline and costs no request.
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" width="32" height="32">
        <rect width="32" height="32" rx="8" fill="#1E3A8A"/>
        <g fill="none" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M16 8.5 22.5 12 16 15.5 9.5 12 16 8.5Z"/>
            <path d="M9.5 12v5.5L16 21l6.5-3.5V12"/>
        </g>
        <circle cx="24" cy="8" r="8" fill="#B91C1C"/>
        <text x="24" y="12" font-family="system-ui, sans-serif" font-size="10" font-weight="700"
              fill="#FFFFFF" text-anchor="middle">${count > 9 ? '9+' : count}</text>
    </svg>`;

    link.href = `data:image/svg+xml,${encodeURIComponent(svg)}`;
}

export function useTabBadge() {
    return {
        set(count) {
            if (typeof document === 'undefined') return;

            const title = ensureBaseTitle();
            document.title = count > 0 ? `(${count}) ${title}` : title;

            paintFavicon(count);
        },

        clear() {
            this.set(0);
        },
    };
}
