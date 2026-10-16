/**
 * Money arrives from the server in minor units (pence, cents, paise), the way
 * it's stored, and is only turned into a decimal for display or editing.
 */

/** 120050, "INR" → "₹1,200.50"; whole amounts drop the decimals. */
export function formatMoney(minor: number, currency: string): string {
    return new Intl.NumberFormat('en', {
        style: 'currency',
        currency,
        minimumFractionDigits: minor % 100 === 0 ? 0 : 2,
        maximumFractionDigits: 2,
    }).format(minor / 100);
}

/** 120050 → "1200.50", for filling an input. */
export function toMajorInput(minor: number): string {
    return minor % 100 === 0 ? String(minor / 100) : (minor / 100).toFixed(2);
}

/** The currency's symbol on its own, e.g. "₹". */
export function currencySymbol(currency: string): string {
    return (
        new Intl.NumberFormat('en', { style: 'currency', currency })
            .formatToParts(0)
            .find((part) => part.type === 'currency')?.value ?? currency
    );
}
