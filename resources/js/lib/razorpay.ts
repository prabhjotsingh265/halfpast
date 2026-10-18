/**
 * Razorpay's pop-up checkout. Their script is loaded only when a customer
 * chooses Razorpay, then opened with the settings the server prepared. The
 * secret key never reaches the browser.
 */

export type RazorpayWidget = {
    gateway: 'razorpay';
    key: string;
    order_id: string;
    amount: number;
    currency: string;
    name: string;
    description: string;
    prefill: { name?: string; email?: string; contact?: string };
    notes: Record<string, string>;
    timeout: number;
    return_url: string;
    cancel_url: string;
};

type RazorpayResponse = {
    razorpay_payment_id: string;
    razorpay_order_id: string;
    razorpay_signature: string;
};

type RazorpayInstance = { open: () => void };

declare global {
    interface Window {
        Razorpay?: new (options: Record<string, unknown>) => RazorpayInstance;
    }
}

const SCRIPT = 'https://checkout.razorpay.com/v1/checkout.js';

function loadScript(): Promise<void> {
    if (window.Razorpay) {
        return Promise.resolve();
    }

    return new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = SCRIPT;
        script.async = true;
        script.onload = () => resolve();
        script.onerror = () =>
            reject(new Error('Razorpay could not be loaded.'));
        document.head.appendChild(script);
    });
}

export async function openRazorpay(
    widget: RazorpayWidget,
    callbacks: {
        paid: (response: RazorpayResponse) => void;
        dismissed: () => void;
    },
): Promise<void> {
    await loadScript();

    if (!window.Razorpay) {
        throw new Error('Razorpay could not be loaded.');
    }

    new window.Razorpay({
        key: widget.key,
        order_id: widget.order_id,
        amount: widget.amount,
        currency: widget.currency,
        name: widget.name,
        description: widget.description,
        prefill: widget.prefill,
        notes: widget.notes,
        timeout: widget.timeout,
        theme: { color: '#EE9433' },
        retry: { enabled: true },
        handler: callbacks.paid,
        modal: { ondismiss: callbacks.dismissed, confirm_close: true },
    }).open();
}
