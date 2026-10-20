import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../../Layouts/AppLayout';
import PageAlerts from '../../../Components/Exams/PageAlerts';
import SuppliesNav from '../../../Components/Supplies/SuppliesNav';
import Pager from '../../../Components/Supplies/Pager';
import { fmtDate, useMoney } from '../../../lib/supplies';

export default function PurchasesIndex({ purchases, filters, suppliers, can }) {
    const money = useMoney();
    const [search, setSearch] = useState(filters.search || '');
    const [from, setFrom] = useState(filters.from || '');
    const [to, setTo] = useState(filters.to || '');

    function visit(overrides = {}) {
        const params = { search, supplier: filters.supplier, status: filters.status, from, to, ...overrides };
        Object.keys(params).forEach((key) => !params[key] && delete params[key]);
        router.get('/supplies/purchases', params, { preserveState: true, replace: true });
    }

    return (
        <AppLayout title="Stock purchases" description="Stock received from suppliers. A purchase is never edited: reverse it if it was a mistake.">
            <Head title="Stock purchases" />
            <PageAlerts />
            <SuppliesNav current="purchases" />
            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div className="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 xl:flex-row xl:items-center xl:justify-between">
                    <h2 className="font-semibold text-slate-900">{purchases.total} purchase{purchases.total === 1 ? '' : 's'}</h2>
                    <div className="flex flex-wrap items-center gap-3">
                        <form onSubmit={(event) => { event.preventDefault(); visit(); }} className="flex flex-wrap items-center gap-2">
                            <input type="search" aria-label="Search purchases" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Number or reference" className="w-44 rounded-xl border border-slate-200 px-3 py-2 text-sm" />
                            <input type="date" aria-label="From" value={from} onChange={(event) => setFrom(event.target.value)} className="rounded-xl border border-slate-200 px-3 py-2 text-sm" />
                            <input type="date" aria-label="To" value={to} onChange={(event) => setTo(event.target.value)} className="rounded-xl border border-slate-200 px-3 py-2 text-sm" />
                            <button className="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Apply</button>
                        </form>
                        <select aria-label="Supplier" value={filters.supplier} onChange={(event) => visit({ supplier: event.target.value })} className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"><option value="">All suppliers</option>{suppliers.map((supplier) => <option key={supplier.id} value={supplier.id}>{supplier.name}</option>)}</select>
                        <select aria-label="Status" value={filters.status} onChange={(event) => visit({ status: event.target.value })} className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"><option value="">All</option><option value="received">Received</option><option value="reversed">Reversed</option></select>
                        {can.create && <Link href="/supplies/purchases/create" className="rounded-xl bg-teal-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-900">Receive stock</Link>}
                    </div>
                </div>
                {purchases.data.length === 0 ? <p className="px-6 py-14 text-center text-sm text-slate-500">No purchases match.</p> : (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[760px] text-left text-sm">
                            <thead className="bg-slate-50 text-xs text-slate-500"><tr><th className="px-5 py-3">Purchase</th><th className="px-5 py-3">Supplier</th><th className="px-5 py-3">Date</th><th className="px-5 py-3 text-right">Total cost</th><th className="px-5 py-3">Status</th><th className="px-5 py-3">Received by</th></tr></thead>
                            <tbody className="divide-y divide-slate-100">
                                {purchases.data.map((purchase) => (
                                    <tr key={purchase.id} className="hover:bg-slate-50/70">
                                        <td className="px-5 py-3.5"><Link href={`/supplies/purchases/${purchase.id}`} className="font-medium text-teal-800 hover:text-teal-950">{purchase.number}</Link>{purchase.reference && <span className="block text-xs text-slate-500">{purchase.reference}</span>}</td>
                                        <td className="px-5 py-3.5 text-slate-700">{purchase.supplier}</td>
                                        <td className="px-5 py-3.5 text-slate-600">{fmtDate(purchase.date)}</td>
                                        <td className="px-5 py-3.5 text-right text-slate-900">{money(purchase.total)}</td>
                                        <td className="px-5 py-3.5"><span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${purchase.status === 'received' ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-600'}`}>{purchase.status === 'received' ? 'Received' : 'Reversed'}</span></td>
                                        <td className="px-5 py-3.5 text-slate-600">{purchase.receivedBy}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                <Pager links={purchases.links} />
            </section>
        </AppLayout>
    );
}