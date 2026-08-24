/**
 * Every status in the app, in plain English, with the colour and icon that go
 * with it. Colour never carries meaning on its own - a pill always shows a
 * colour AND an icon AND a word, because people are colour-blind, screens are
 * bad, and kitchens are bright.
 *
 * This is the only place these words are defined. Nothing else hard-codes them.
 */
export const STATUS = {
    draft: {
        label: 'Not sent',
        icon: 'FileEdit',
        pill: 'text-ink-soft bg-page border-line',
        spine: '#9CA3AF',
    },
    waiting: {
        label: 'Waiting',
        icon: 'Clock',
        pill: 'text-waiting bg-waiting-bg border-waiting/20',
        spine: '#B45309',
    },
    approved: {
        label: 'Approved',
        icon: 'CheckCircle2',
        pill: 'text-approved bg-approved-bg border-approved/20',
        spine: '#15803D',
    },
    partial: {
        label: 'Less than asked',
        icon: 'MinusCircle',
        pill: 'text-partial bg-partial-bg border-partial/20',
        spine: '#C2410C',
    },
    rejected: {
        label: 'Not approved',
        icon: 'XCircle',
        pill: 'text-rejected bg-rejected-bg border-rejected/20',
        spine: '#B91C1C',
    },
    sent: {
        label: 'On the way',
        icon: 'Truck',
        pill: 'text-primary bg-primary-light border-primary/20',
        spine: '#1F5EFF',
    },
    received: {
        label: 'Arrived',
        icon: 'PackageCheck',
        pill: 'text-approved bg-approved-bg border-approved/20',
        spine: '#15803D',
    },
    closed: {
        label: 'Done',
        icon: 'Check',
        pill: 'text-ink-soft bg-page border-line',
        spine: '#9CA3AF',
    },
    cancelled: {
        label: 'Cancelled',
        icon: 'Ban',
        pill: 'text-ink-soft bg-page border-line',
        spine: '#9CA3AF',
    },
    late: {
        label: 'Late',
        icon: 'AlertTriangle',
        pill: 'text-partial bg-partial-bg border-partial/20',
        spine: '#C2410C',
    },
    low: {
        label: 'Running low',
        icon: 'TrendingDown',
        pill: 'text-partial bg-partial-bg border-partial/20',
        spine: '#C2410C',
    },
};

const FALLBACK = {
    label: 'Unknown',
    icon: 'HelpCircle',
    pill: 'text-ink-soft bg-page border-line',
    spine: '#9CA3AF',
};

export function statusMeta(status) {
    return STATUS[status] ?? FALLBACK;
}

export function statusLabel(status) {
    return statusMeta(status).label;
}
