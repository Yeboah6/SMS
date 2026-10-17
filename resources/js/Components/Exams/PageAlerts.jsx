import { usePage } from '@inertiajs/react';

/** Flash status plus any server-side errors (including service-level ones). */
export default function PageAlerts() {
    const { status, errors = {} } = usePage().props;
    const messages = [...new Set(Object.values(errors).flat())];

    return (
        <>
            {status && <div role="status" className="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{status}</div>}
            {messages.length > 0 && (
                <div role="alert" className="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    {messages.map((message) => <p key={message}>{message}</p>)}
                </div>
            )}
        </>
    );
}