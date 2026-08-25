/**
 * One way to write money, so two numbers on the same screen never disagree
 * about how they are grouped or how many paise they show.
 *
 * Indian grouping throughout (52,98,583, not 5,298,583). A column of amounts
 * always shows the same number of decimals: mixing ₹2,016 with ₹129.60 makes
 * the column impossible to scan down.
 */
export function money(value, { symbol = '₹', decimals = 2 } = {}) {
    const amount = Number(value ?? 0);
    const sign = amount < 0 ? '-' : '';

    const text = Math.abs(amount).toLocaleString('en-IN', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    });

    return `${sign}${symbol}${text}`;
}

/** Whole rupees. For totals big enough that paise are noise. */
export function rupees(value, symbol = '₹') {
    return money(value, { symbol, decimals: 0 });
}
