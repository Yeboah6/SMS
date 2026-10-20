import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import PageAlerts from '../../Components/Exams/PageAlerts';
import SuppliesNav from '../../Components/Supplies/SuppliesNav';
import Pill from '../../Components/Supplies/Pill';
import { PAYMENT_STATUS, STOCK_STATUS, fmtDateTime, useMoney } from '../../lib/supplies';

export default function SuppliesDashboard({ stats, recentSales, attention, can }) {
    const money = useMoney();

    const cards = [
        ['Items in inventory', stats.items, null],
        ['Stock value (at cost)', money(stats.stockCost), `${money(stats.stockRetail)} at selling price`],
        ['Low stock', stats.lowStock, 'At or below reorder level', stats.lowStock > 0 ? 'text-amber-700' : ''],
        ['Out of stock', stats.outOfStock, 'Cannot be sold', stats.outOfStock > 0 ? 'text-rose-700' : ''],
        ['Sales today', money(stats.salesToday.net), `${stats.salesToday.count} sale${stats.salesToday.count === 1 ? '' : 's'} · ${money(stats.salesToday.collected)} collected`],
        ['Sales this month', money(stats.salesMonth.net), `${stats.salesMonth.count} sale${stats.salesMonth.count === 1 ? '' : 's'} · ${money(stats.salesMonth.collected)} collected`],
        ['Outstanding balances', money(stats.outstanding.balance), `${stats.outstanding.count} sale${stats.outstanding.count === 1 ? '' : 's'} still owing`, stats.outstanding.balance > 0 ? 'text-rose-700' : ''],
    ];

    return (
        <AppLayout title="Books & supplies" description="Stock, sales to students and class requirements at a glance.">
            <Head title="Books & supplies" />
            <PageAlerts />
            <SuppliesNav current="dashboard" />

            <div className="mb-6 flex flex-wrap gap-3">
                {can.sell && <Link href="/supplies/sales/create" className="rounded-xl bg-teal-800 px-5 py-3 text-sm font-semibold text-white hover:bg-teal-900">New sale</Link>}
                {can.receive && <Link href="/supplies/purchases/create" className="rounded-xl border border-teal-800 px-5 py-3 text-sm font-semibold text-teal-800 hover:bg-teal-50">Receive stock</Link>}
                {can.reports && <Link href="/supplies/reports" className="rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reports</Link>}
            </div>

            <section className="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                {cards.map(([label, value, hint, tone]) => (
                    <article key={label} className="rounded-2xl border border-slate-200 bg-white p-5">
                        <p className="text-sm text-slate-500">{label}</p>
                        <p className={`mt-2 text-2xl font-semibold tracking-tight ${tone || 'text-slate-900'}`}>{value}</p>
                        {hint && <p className="mt-1 text-xs text-slate-500">{hint}</p>}
                    </article>
                ))}
            </section>

            <div className="grid gap-6 xl:grid-cols-2">
                <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <div className="flex items-center justify-between gap-3">
                        <h2 className="font-semibold text-slate-900">Needs restocking</h2>
                        {can.items && <Link href="/supplies/items?stock=low" className="text-sm font-semibold text-teal-800 hover:text-teal-950">View all</Link>}
                    </div>
                    {attention.length === 0 ? (
                        <p className="mt-4 rounded-xl bg-slate-50 px-3 py-4 text-sm text-slate-500">Every active item is above its reorder level.</p>
                    ) : (
                        <ul className="mt-4 divide-y divide-slate-100 text-sm">
                            {attention.map((item) => (
                                <li key={item.id} className="flex flex-wrap items-center justify-between gap-3 py-2.5">
                                    <span><Link href={`/supplies/items/${item.id}`} className="font-medium text-teal-800 hover:text-teal-950">{item.name}</Link><span className="block text-xs text-slate-500">{item.sku} · reorder at {item.reorderLevel}</span></span>
                                    <span className="flex items-center gap-3"><span className="font-semibold text-slate-900">{item.quantity} {item.unit}</span><Pill map={STOCK_STATUS} value={item.stockStatus} /></span>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <div className="flex items-center justify-between gap-3">
                        <h2 className="font-semibold text-slate-900">Recent sales</h2>
                        <Link href="/supplies/sales" className="text-sm font-semibold text-teal-800 hover:text-teal-950">View all</Link>
                    </div>
                    {recentSales.length === 0 ? (
                        <p className="mt-4 rounded-xl bg-slate-50 px-3 py-4 text-sm text-slate-500">No sales yet.</p>
                    ) : (
                        <ul className="mt-4 divide-y divide-slate-100 text-sm">
                            {recentSales.map((sale) => (
                                <li key={sale.id} className="flex flex-wrap items-center justify-between gap-3 py-2.5">
                                    <span><Link href={`/supplies/sales/${sale.id}`} className="font-medium text-teal-800 hover:text-teal-950">{sale.saleNumber}</Link><span className="block text-xs text-slate-500">{sale.student.name} · {fmtDateTime(sale.soldAt)}</span></span>
                                    <span className="flex items-center gap-3"><span className="font-semibold text-slate-900">{money(sale.netDue)}</span><Pill map={PAYMENT_STATUS} value={sale.paymentStatus} /></span>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}