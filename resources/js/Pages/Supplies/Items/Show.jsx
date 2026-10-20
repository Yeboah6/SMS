import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import PageAlerts from '../../../Components/Exams/PageAlerts';
import SuppliesNav from '../../../Components/Supplies/SuppliesNav';
import Pill from '../../../Components/Supplies/Pill';
import Pager from '../../../Components/Supplies/Pager';
import { STOCK_STATUS, fmtDateTime, useMoney } from '../../../lib/supplies';

export default function ItemsShow({ item, movements, unitsSold, can }) {
    const money = useMoney();
    const margin = item.sellingPrice - item.costPrice;

    return (
        <AppLayout title={item.name} description={`${item.sku} · ${item.category}`}>
            <Head title={item.name} />
            <PageAlerts />
            <SuppliesNav current="items" />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <Link href="/supplies/items" className="text-sm font-semibold text-teal-800 hover:text-teal-950">Back to inventory</Link>
                <div className="flex flex-wrap items-center gap-4 text-sm font-semibold">
                    {can.adjust && <Link href={`/supplies/items/${item.id}/adjust`} className="text-teal-800 hover:text-teal-950">Adjust stock</Link>}
                    {can.update && <Link href={`/supplies/items/${item.id}/edit`} className="text-teal-800 hover:text-teal-950">Edit item</Link>}
                </div>
            </div>

            <section className="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <Card label="On hand"><span className="text-2xl font-semibold text-slate-900">{item.quantity}</span> <span className="text-sm text-slate-500">{item.unit}</span><div className="mt-2 flex items-center gap-2"><Pill map={STOCK_STATUS} value={item.stockStatus} /><span className="text-xs text-slate-500">reorder at {item.reorderLevel}</span></div></Card>
                <Card label="Selling price"><span className="text-2xl font-semibold text-slate-900">{money(item.sellingPrice)}</span><p className="mt-2 text-xs text-slate-500">Cost {money(item.costPrice)} · margin {money(margin)}</p></Card>
                <Card label="Stock value"><span className="text-2xl font-semibold text-slate-900">{money(item.stockValue)}</span><p className="mt-2 text-xs text-slate-500">At cost</p></Card>
                <Card label="Units sold"><span className="text-2xl font-semibold text-slate-900">{unitsSold}</span><p className="mt-2 text-xs text-slate-500">Net of returns</p></Card>
            </section>

            {item.description && <section className="mb-6 rounded-2xl border border-slate-200 bg-white p-5 text-sm text-slate-700">{item.description}{!item.isActive && <p className="mt-3 font-semibold text-slate-600">This item is inactive and cannot be sold.</p>}</section>}

            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div className="border-b border-slate-100 px-5 py-4"><h2 className="font-semibold text-slate-900">Stock ledger</h2><p className="mt-1 text-sm text-slate-500">Every change to the quantity, newest first. Rows are never edited or deleted.</p></div>
                {movements.data.length === 0 ? <p className="px-6 py-12 text-center text-sm text-slate-500">No movements yet.</p> : (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[760px] text-left text-sm">
                            <thead className="bg-slate-50 text-xs text-slate-500"><tr><th className="px-5 py-3">When</th><th className="px-5 py-3">Movement</th><th className="px-5 py-3 text-right">Change</th><th className="px-5 py-3 text-right">Balance</th><th className="px-5 py-3">Reference</th><th className="px-5 py-3">By</th></tr></thead>
                            <tbody className="divide-y divide-slate-100">
                                {movements.data.map((movement) => (
                                    <tr key={movement.id}>
                                        <td className="px-5 py-3 text-slate-600">{fmtDateTime(movement.at)}</td>
                                        <td className="px-5 py-3 text-slate-800">{movement.typeLabel}{movement.reason && <span className="block text-xs text-slate-500">{movement.reason}</span>}</td>
                                        <td className={`px-5 py-3 text-right font-semibold ${movement.change > 0 ? 'text-emerald-700' : 'text-rose-700'}`}>{movement.change > 0 ? `+${movement.change}` : movement.change}</td>
                                        <td className="px-5 py-3 text-right text-slate-700">{movement.before} → {movement.after}</td>
                                        <td className="px-5 py-3">{movement.referenceUrl ? <Link href={movement.referenceUrl} className="text-teal-800 hover:text-teal-950">{movement.reference}</Link> : '—'}</td>
                                        <td className="px-5 py-3 text-slate-600">{movement.by}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                <Pager links={movements.links} />
            </section>
        </AppLayout>
    );
}

function Card({ label, children }) {
    return <article className="rounded-2xl border border-slate-200 bg-white p-5"><p className="mb-1 text-sm text-slate-500">{label}</p>{children}</article>;
}