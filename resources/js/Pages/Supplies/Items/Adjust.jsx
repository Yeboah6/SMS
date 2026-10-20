import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import SuppliesNav from '../../../Components/Supplies/SuppliesNav';
import { Field, inputClass } from '../../Auth/Login';

const HELP = {
    adjustment_in: 'Stock found or counted above the ledger (a stock-take correction).',
    adjustment_out: 'Stock counted below the ledger (a stock-take correction).',
    damaged: 'Damaged, lost or stolen units. Taken off the shelf.',
    supplier_return: 'Units sent back to the supplier.',
};

export default function ItemsAdjust({ item, types }) {
    const form = useForm({ type: types[0]?.key || '', quantity: '', reason: '' });
    const sign = form.data.type === 'adjustment_in' ? 1 : -1;
    const after = form.data.quantity ? item.quantity + sign * Number(form.data.quantity) : null;

    return (
        <AppLayout title={`Adjust stock · ${item.name}`} description="Every change is written to the stock ledger with who made it and why.">
            <Head title="Adjust stock" />
            <SuppliesNav current="items" />
            <form onSubmit={(event) => { event.preventDefault(); form.post(`/supplies/items/${item.id}/adjust`); }} className="grid max-w-2xl gap-4 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <p className="text-sm text-slate-600">{item.sku} · currently <span className="font-semibold text-slate-900">{item.quantity} {item.unit}</span> on hand</p>
                <Field label="Type of adjustment" error={form.errors.type}>
                    <select value={form.data.type} onChange={(event) => form.setData('type', event.target.value)} className={inputClass(form.errors.type)}>
                        {types.map((type) => <option key={type.key} value={type.key}>{type.label}</option>)}
                    </select>
                </Field>
                <p className="-mt-2 text-xs text-slate-500">{HELP[form.data.type]}</p>
                <Field label="Quantity" error={form.errors.quantity}>
                    <input type="number" min="1" value={form.data.quantity} onChange={(event) => form.setData('quantity', event.target.value)} className={inputClass(form.errors.quantity)} required />
                </Field>
                {after !== null && <p className={`text-sm ${after < 0 ? 'text-rose-700' : 'text-slate-600'}`}>{after < 0 ? 'This would take stock below zero and will be refused.' : `Stock will be ${after} ${item.unit} after this adjustment.`}</p>}
                <Field label="Reason" error={form.errors.reason}>
                    <textarea rows={3} value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} className={inputClass(form.errors.reason)} minLength={5} maxLength={1000} required />
                </Field>
                <div className="flex flex-wrap gap-3">
                    <button disabled={form.processing} className="rounded-xl bg-teal-800 px-5 py-3 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-60">{form.processing ? 'Saving…' : 'Record adjustment'}</button>
                    <Link href={`/supplies/items/${item.id}`} className="rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</Link>
                </div>
            </form>
        </AppLayout>
    );
}