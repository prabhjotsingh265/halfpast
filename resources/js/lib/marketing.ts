/**
 * Sample data for the home page. None of it is real business data: it only
 * makes the interactive previews look like the visitor's own business.
 */

export type BusinessKind = 'salon' | 'barber' | 'clinic' | 'tutor';

export type SampleService = {
    name: string;
    minutes: number;
    /** Price in pounds; converted for other currencies. */
    price: number;
};

export type BusinessType = {
    kind: BusinessKind;
    label: string;
    description: string;
    placeholder: string;
    staff: string;
    services: SampleService[];
};

export const businessTypes: BusinessType[] = [
    {
        kind: 'salon',
        label: 'Salon',
        description: 'Hair salon',
        placeholder: 'Glow Studio',
        staff: 'Ben',
        services: [
            { name: 'Haircut', minutes: 30, price: 30 },
            { name: 'Colour', minutes: 120, price: 95 },
            { name: 'Blow-dry', minutes: 45, price: 35 },
        ],
    },
    {
        kind: 'barber',
        label: 'Barber',
        description: 'Barbershop',
        placeholder: 'Sharp & Co',
        staff: 'Rafi',
        services: [
            { name: 'Cut', minutes: 30, price: 22 },
            { name: 'Cut and beard', minutes: 45, price: 30 },
            { name: 'Hot towel shave', minutes: 30, price: 25 },
        ],
    },
    {
        kind: 'clinic',
        label: 'Clinic',
        description: 'Physiotherapy clinic',
        placeholder: 'Northside Physio',
        staff: 'Dr Patel',
        services: [
            { name: 'First assessment', minutes: 45, price: 60 },
            { name: 'Follow-up', minutes: 30, price: 45 },
            { name: 'Sports massage', minutes: 60, price: 55 },
        ],
    },
    {
        kind: 'tutor',
        label: 'Tutor',
        description: 'Private tutor',
        placeholder: "Ms Rivera's Maths",
        staff: 'Ms Rivera',
        services: [
            { name: 'Maths lesson', minutes: 60, price: 35 },
            { name: 'Exam prep', minutes: 90, price: 50 },
            { name: 'Trial lesson', minutes: 30, price: 15 },
        ],
    },
];

export type Currency = 'GBP' | 'USD' | 'EUR' | 'INR';

/**
 * How sample prices scale from pounds. Not exchange rates: they aim for what a
 * customer would expect to pay locally, so an Indian salon sees Indian prices.
 */
const RATES: Record<Currency, number> = {
    GBP: 1,
    USD: 1.3,
    EUR: 1.15,
    INR: 40,
};

/** A best guess at the visitor's currency, from their time zone. */
export function guessCurrency(): Currency {
    const zone = Intl.DateTimeFormat().resolvedOptions().timeZone ?? '';

    if (zone === 'Asia/Kolkata' || zone === 'Asia/Calcutta') {
        return 'INR';
    }

    if (zone === 'Europe/London') {
        return 'GBP';
    }

    if (zone.startsWith('America/')) {
        return 'USD';
    }

    if (zone.startsWith('Europe/')) {
        return 'EUR';
    }

    return 'GBP';
}

/** A sample price in pounds, converted and rounded to a number a shop would charge. */
export function money(pounds: number, currency: Currency): string {
    const raw = pounds * RATES[currency];
    const step = currency === 'INR' ? 50 : 1;
    const amount = Math.max(step, Math.round(raw / step) * step);

    return new Intl.NumberFormat('en', {
        style: 'currency',
        currency,
        maximumFractionDigits: 0,
    }).format(amount);
}

/** The deposit shown in previews: a fifth of the price, at least a small fixed amount. */
export function sampleDeposit(price: number): number {
    return Math.max(5, Math.round(price * 0.2));
}

/** A small, stable hash, so each business name gets its own pattern of busy slots. */
export function hash(text: string): number {
    let h = 2166136261;

    for (let i = 0; i < text.length; i++) {
        h ^= text.charCodeAt(i);
        h = Math.imul(h, 16777619);
    }

    return h >>> 0;
}
