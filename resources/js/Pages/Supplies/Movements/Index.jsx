import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../../Layouts/AppLayout';
import SuppliesNav from '../../../Components/Supplies/SuppliesNav';
import Pager from '../../../Components/Supplies/Pager';
import { fmtDateTime } from '../../../lib/supplies';

export default function MovementsIndex({ movements, filters, types }) {
    const [search, setSearch] = useState(filters.search || '');
    const [from, setFrom] = useState(filters.from || '');
    const [to, setTo] = useState(filters.to || '');

    function visit(overrides = {}) {
        const params = { search, type: filters.type, from, to, ...overrides };
        Object.keys(params).forEach((key) => !params[key] && delete params[key]);
        router.get('/supplies/stock-movements', params, { preserveState: true, replace: true });
    }

    return (
        <AppLayout title="Stock ledger" description="Every stock movement across all items: received, sold, damaged, adjusted and returned.">
            <Head title="Stock ledger" />
            <SuppliesNav current="items" />
            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <form onSubmit={(event) => { event.preventDefault(); visit(); }} className="flex flex-wrap items-end gap-3 border-b border-slate-100 px-5 py-4">
                    <input type="search" aria-label="Search items" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Item name or SKU" className="w-48 rounded-xl border border-slate-200 px-3 py-2 text-sm" />
                    <select aria-label="Movement type" value={filters.type} onChange={(event) => visit({ type: event.target.value })} className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"><option value="">All movements</option>{types.map((type) => <option key={type.key} value={type.key}>{type.label}</option>)}</select>
                    <label className="text-xs text-slate-500">From<input type="date" value={from} onChange={(event) => setFrom(event.target.value)} className="ml-2 rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-800" /></label>
                    <label className="text-xs text-slate-500">To<input type="date" value={to} onChange={(event) => setTo(event.target.value)} className="ml-2 rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-800" /></label>
                    <button className="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Apply</button>
                </form>
                {movements.data.length === 0 ? <p className="px-6 py-14 text-center text-sm text-slate-500">No movements match.</p> : (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[860px] text-left text-sm">
                            <thead className="bg-slate-50 text-xs text-slate-500"><tr><th className="px-5 py-3">When</th><th className="px-5 py-3">Item</th><th className="px-5 py-3">Movement</th><th className="px-5 py-3 text-right">Change</th><th className="px-5 py-3 text-right">Balance</th><th className="px-5 py-3">Reference</th><th className="px-5 py-3">By</th></tr></thead>
                            <tbody className="divide-y divide-slate-100">
                                {movements.data.map((movement) => (
                                    <tr key={movement.id}>
                                        <td className="px-5 py-3 text-slate-600">{fmtDateTime(movement.at)}</td>
                                        <td className="px-5 py-3"><Link href={`/supplies/items/${movement.itemId}`} className="font-medium text-teal-800 hover:text-teal-950">{movement.itemName}</Link><span className="block text-xs text-slate-500">{movement.sku}</span></td>
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