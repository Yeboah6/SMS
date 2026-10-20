import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import PageAlerts from '../../../Components/Exams/PageAlerts';
import SuppliesNav from '../../../Components/Supplies/SuppliesNav';
import { Field, inputClass } from '../../Auth/Login';
import { fmtDate, fmtDateTime, useMoney } from '../../../lib/supplies';

export default function PurchasesShow({ purchase, can }) {
    const money = useMoney();
    const form = useForm({ reason: '' });

    function reverse(event) {
        event.preventDefault();
        if (window.confirm(`Reverse ${purchase.number}? The received stock is taken back out of inventory.`)) {
            form.post(`/supplies/purchases/${purchase.id}/reverse`, { preserveScroll: true });
        }
    }

    return (
        <AppLayout title={purchase.number} description={`${purchase.supplier} · ${fmtDate(purchase.date)}`}>
            <Head title={purchase.number} />
            <PageAlerts />
            <SuppliesNav current="purchases" />
            <div className="mb-6"><Link href="/supplies/purchases" className="text-sm font-semibold text-teal-800 hover:text-teal-950">Back to purchases</Link></div>

            <section className="mb-6 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3"><h2 className="font-semibold text-slate-900">Delivery</h2><span className={`rounded-full px-3 py-1 text-xs font-semibold ${purchase.status === 'received' ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-600'}`}>{purchase.status === 'received' ? 'Received' : 'Reversed'}</span></div>
                <dl className="mt-4 grid gap-4 text-sm sm:grid-cols-2 xl:grid-cols-4">
                    {[['Supplier', purchase.supplier], ['Date', fmtDate(purchase.date)], ['Reference', purchase.reference], ['Received by', purchase.receivedBy], ['Total cost', money(purchase.total)]].map(([label, value]) => <div key={label}><dt className="text-xs font-medium text-slate-500">{label}</dt><dd className="mt-1 text-slate-900">{value || '—'}</dd></div>)}
                    {purchase.notes && <div className="sm:col-span-2 xl:col-span-4"><dt className="text-xs font-medium text-slate-500">Notes</dt><dd className="mt-1 text-slate-900">{purchase.notes}</dd></div>}
                </dl>
                {purchase.status === 'reversed' && <p role="status" className="mt-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">Reversed {fmtDateTime(purchase.reversedAt)} by {purchase.reversedBy}: {purchase.reversalReason}</p>}
            </section>

            <section className="mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <table className="w-full text-left text-sm"><thead className="bg-slate-50 text-xs text-slate-500"><tr><th className="px-5 py-3">Item</th><th className="px-5 py-3 text-right">Quantity</th><th className="px-5 py-3 text-right">Unit cost</th><th className="px-5 py-3 text-right">Line total</th></tr></thead>
                    <tbody className="divide-y divide-slate-100">{purchase.lines.map((line) => <tr key={line.id}><td className="px-5 py-3"><Link href={`/supplies/items/${line.itemId}`} className="font-medium text-teal-800 hover:text-teal-950">{line.name}</Link><span className="block text-xs text-slate-500">{line.sku}</span></td><td className="px-5 py-3 text-right">{line.quantity} {line.unit}</td><td className="px-5 py-3 text-right">{money(line.unitCost)}</td><td className="px-5 py-3 text-right text-slate-900">{money(line.lineTotal)}</td></tr>)}</tbody>
                </table>
            </section>

            {can.reverse && (
                <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <h2 className="font-semibold text-slate-900">Reverse this purchase</h2>
                    <p className="mt-1 text-sm text-slate-500">Use this if the delivery was recorded in error. It is refused if any of the stock has already been sold.</p>
                    <form onSubmit={reverse} className="mt-4 max-w-xl"><Field label="Reason" error={form.errors.reason || form.errors.purchase || form.errors.quantity}><textarea rows={2} value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} className={inputClass(form.errors.reason)} minLength={5} required /></Field><button disabled={form.processing} className="mt-3 rounded-xl bg-rose-700 px-5 py-3 text-sm font-semibold text-white hover:bg-rose-800 disabled:opacity-60">Reverse purchase</button></form>
                </section>
            )}
        </AppLayout>
    );
}