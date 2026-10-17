import { useForm } from '@inertiajs/react';
import { Field, inputClass } from '../../Pages/Auth/Login';
import { PAYMENT_METHODS, humanize } from '../../lib/fees';

/** Records one payment against one invoice. The receipt is issued automatically. */
export default function PaymentForm({ invoice, today }) {
    const form = useForm({
        amount: invoice.outstandingAmount.toFixed(2),
        payment_method: 'cash',
        transaction_reference: '',
        payment_date: today,
        notes: '',
    });

    function record(event) {
        event.preventDefault();
        form.post(`/invoices/${invoice.id}/payments`, { preserveScroll: true });
    }

    return (
        <form onSubmit={record} className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <Field label="Amount received" error={form.errors.amount}>
                <input type="number" min="0.01" max={invoice.outstandingAmount} step="0.01" value={form.data.amount} onChange={(event) => form.setData('amount', event.target.value)} className={inputClass(form.errors.amount)} required />
            </Field>
            <Field label="Method" error={form.errors.payment_method}>
                <select value={form.data.payment_method} onChange={(event) => form.setData('payment_method', event.target.value)} className={inputClass(form.errors.payment_method)}>
                    {PAYMENT_METHODS.map((method) => <option key={method} value={method}>{humanize(method)}</option>)}
                </select>
            </Field>
            <Field label="Payment date" error={form.errors.payment_date}>
                <input type="date" max={today} value={form.data.payment_date} onChange={(event) => form.setData('payment_date', event.target.value)} className={inputClass(form.errors.payment_date)} required />
            </Field>
            <Field label="Transaction reference" error={form.errors.transaction_reference}>
                <input value={form.data.transaction_reference} onChange={(event) => form.setData('transaction_reference', event.target.value)} className={inputClass(form.errors.transaction_reference)} maxLength={100} />
            </Field>
            <div className="sm:col-span-2 xl:col-span-3">
                <Field label="Notes" error={form.errors.notes}>
                    <input value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} className={inputClass(form.errors.notes)} maxLength={2000} />
                </Field>
            </div>
            <div className="flex items-end">
                <button type="submit" disabled={form.processing} className="w-full rounded-xl bg-teal-800 px-4 py-3 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-60">{form.processing ? 'Saving…' : 'Record payment'}</button>
            </div>
            {form.errors.invoice && <p role="alert" className="text-sm text-rose-700 sm:col-span-2 xl:col-span-4">{form.errors.invoice}</p>}
        </form>
    );
}