import { usePage } from '@inertiajs/react';

export default function StatusBanner() {
    const { status, errors } = usePage().props;
    const error = errors?.schoolClass || errors?.section || errors?.subject;

    return (
        <>
            {status && <div role="status" className="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{status}</div>}
            {error && <div role="alert" className="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{error}</div>}
        </>
    );
}