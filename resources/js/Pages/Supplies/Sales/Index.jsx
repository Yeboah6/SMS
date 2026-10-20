import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../../Layouts/AppLayout';
import PageAlerts from '../../../Components/Exams/PageAlerts';
import SuppliesNav from '../../../Components/Supplies/SuppliesNav';
import Pill from '../../../Components/Supplies/Pill';
import Pager from '../../../Components/Supplies/Pager';
import { PAYMENT_STATUS, SALE_STATUS, fmtDateTime, useMoney } from '../../../lib/supplies';

const TABS = [['', 'All'], ['outstanding', 'Outstanding'], ['paid', 'Paid'], ['returned', 'Returned'], ['cancelled', 'Cancelled']];

export default function SalesIndex({ sales, filters, counts, can }) {
    const money = useMoney();
    const [search, setSearch] = useState(filters.search || '');
    const [from, setFrom] = useState(filters.from || '');
    const [to, setTo] = useState(filters.to || '');

    function visit(overrides = {}) {
        const params = { search, view: filters.view, from, to, ...overrides };
        Object.keys(params).forEach((key) => !params[key] && delete params[key]);
        router.get('/supplies/sales', params, { preserveState: true, replace: true });
    }

    return (
        <AppLayout title="Sales" description="Books and supplies sold to students.">
            <Head title="Sales" />
            <PageAlerts />
            <SuppliesNav current="sales" />

            <nav aria-label="Sales view" className="mb-5 flex gap-2 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-2">
                {TABS.map(([value, label]) => (
                    <button key={value || 'all'} type="button" aria-pressed={filters.view === value} onClick={() => visit({ view: value })} className={`shrink-0 rounded-xl px-4 py-2.5 text-sm font-semibold ${filters.view === value ? 'bg-teal-800 text-white' : 'text-slate-600 hover:bg-slate-100'}`}>
                        {label}{counts[value] !== undefined && <span className={`ml-1.5 ${filters.view === value ? 'text-teal-100' : 'text-slate-400'}`}>{counts[value]}</span>}
                    </button>
                ))}
            </nav>

            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div className="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 xl:flex-row xl:items-center xl:justify-between">
                    <h2 className="font-semibold text-slate-900">{sales.total.toLocaleString()} sale{sales.total === 1 ? '' : 's'}</h2>
                    <div className="flex flex-wrap items-center gap-3">
                        <form onSubmit={(event) => { event.preventDefault(); visit(); }} className="flex flex-wrap items-center gap-2">
                            <input type="search" aria-label="Search sales" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Sale no., student or admission no." className="w-60 rounded-xl border border-slate-200 px-3 py-2 text-sm" />
                            <input type="date" aria-label="From" value={from} onChange={(event) => setFrom(event.target.value)} className="rounded-xl border border-slate-200 px-3 py-2 text-sm" />
                            <input type="date" aria-label="To" value={to} onChange={(event) => setTo(event.target.value)} className="rounded-xl border border-slate-200 px-3 py-2 text-sm" />
                            <button className="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Apply</button>
                        </form>
                        {can.create && <Link href="/supplies/sales/create" className="rounded-xl bg-teal-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-900">New sale</Link>}
                    </div>
                </div>
                {sales.data.length === 0 ? <p className="px-6 py-14 text-center text-sm text-slate-500">No sales match.</p> : (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[900px] text-left text-sm">
                            <thead className="bg-slate-50 text-xs text-slate-500"><tr><th className="px-5 py-3">Sale</th><th className="px-5 py-3">Student</th><th className="px-5 py-3 text-right">Amount</th><th className="px-5 py-3 text-right">Balance</th><th className="px-5 py-3">Payment</th><th className="px-5 py-3">Status</th><th className="px-5 py-3">Sold by</th></tr></thead>
                            <tbody className="divide-y divide-slate-100">
                                {sales.data.map((sale) => (
                                    <tr key={sale.id} className="hover:bg-slate-50/70">
                                        <td className="px-5 py-3.5"><Link href={`/supplies/sales/${sale.id}`} className="font-medium text-teal-800 hover:text-teal-950">{sale.saleNumber}</Link><span className="block text-xs text-slate-500">{fmtDateTime(sale.soldAt)}</span></td>
                                        <td className="px-5 py-3.5 text-slate-700"><Link href={`/supplies/students/${sale.student.id}`} className="hover:text-teal-800">{sale.student.name}</Link><span className="block text-xs text-slate-500">{sale.student.admissionNumber}{sale.className ? ` · ${sale.className}` : ''}</span></td>
                                        <td className="px-5 py-3.5 text-right text-slate-900">{money(sale.netDue)}</td>
                                        <td className={`px-5 py-3.5 text-right font-medium ${sale.balance > 0 ? 'text-rose-700' : 'text-slate-500'}`}>{money(sale.balance)}</td>
                                        <td className="px-5 py-3.5"><Pill map={PAYMENT_STATUS} value={sale.paymentStatus} /></td>
                                        <td className="px-5 py-3.5"><Pill map={SALE_STATUS} value={sale.status} /></td>
                                        <td className="px-5 py-3.5 text-slate-600">{sale.soldBy}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                <Pager links={sales.links} />
            </section>
        </AppLayout>
    );
}