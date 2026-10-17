import { usePage } from '@inertiajs/react';

/** Formats an amount in the school's currency (from the shared feeSettings prop). */
export function useMoney() {
    const { feeSettings } = usePage().props;

    return (value) => new Intl.NumberFormat(undefined, { style: 'currency', currency: feeSettings.currency_code }).format(value || 0);
}

export const dateLabel = (value) => new Date(`${value}T00:00:00`).toLocaleDateString();
export const humanize = (value) => String(value ?? '').replaceAll('_', ' ');

export const STATUS_LABELS = { unpaid: 'Unpaid', partially_paid: 'Partially paid', paid: 'Paid' };

export function statusStyle(status) {
    if (status === 'paid') return 'bg-emerald-50 text-emerald-800';
    if (status === 'partially_paid') return 'bg-amber-50 text-amber-800';
    return 'bg-slate-100 text-slate-700';
}

export const PAYMENT_METHODS = ['cash', 'bank_transfer', 'card', 'mobile_money', 'cheque', 'other'];

export function dueDateAfter(days) {
    const date = new Date();
    date.setDate(date.getDate() + Number(days));
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}