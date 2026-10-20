import { usePage } from '@inertiajs/react';

/** Formats an amount in the school's currency (shared feeSettings prop, set in School settings). */
export function useMoney() {
    const { feeSettings } = usePage().props;

    return (value) => new Intl.NumberFormat(undefined, { style: 'currency', currency: feeSettings?.currency_code || 'USD' }).format(value || 0);
}

/** Same permission rule as the sidebar: Super Admin passes everything. */
export function useCan() {
    const { auth } = usePage().props;
    const user = auth.user;

    return (permission) => user.roles.includes('Super Admin') || user.permissions.includes(permission);
}

export const fmtDate = (value) => (value ? new Date(`${String(value).slice(0, 10)}T00:00:00`).toLocaleDateString() : '—');
export const fmtDateTime = (value) => (value ? new Date(String(value).replace(' ', 'T')).toLocaleString() : '—');
export const humanize = (value) => String(value ?? '').replaceAll('_', ' ');

export const SALE_STATUS = {
    completed: ['Completed', 'bg-emerald-50 text-emerald-800'],
    partially_returned: ['Partly returned', 'bg-amber-50 text-amber-800'],
    returned: ['Returned', 'bg-slate-100 text-slate-700'],
    cancelled: ['Cancelled', 'bg-rose-50 text-rose-800'],
};

export const PAYMENT_STATUS = {
    paid: ['Paid', 'bg-emerald-50 text-emerald-800'],
    partial: ['Part paid', 'bg-amber-50 text-amber-800'],
    unpaid: ['Unpaid', 'bg-rose-50 text-rose-800'],
    void: ['Void', 'bg-slate-100 text-slate-600'],
};

export const STOCK_STATUS = {
    ok: ['In stock', 'bg-emerald-50 text-emerald-800'],
    low: ['Low', 'bg-amber-50 text-amber-800'],
    out: ['Out of stock', 'bg-rose-50 text-rose-800'],
};

export const REQUIREMENT_STATUS = {
    complete: ['Complete', 'bg-emerald-50 text-emerald-800'],
    partial: ['Partial', 'bg-amber-50 text-amber-800'],
    outstanding: ['Outstanding', 'bg-rose-50 text-rose-800'],
    external: ['Bought elsewhere', 'bg-sky-50 text-sky-800'],
};