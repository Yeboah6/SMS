import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../../Layouts/AppLayout';
import SuppliesNav from '../../../Components/Supplies/SuppliesNav';
import { Field, inputClass } from '../../Auth/Login';
import { STOCK_STATUS, useMoney } from '../../../lib/supplies';

const HINTS = {
    sales: 'Net sales (after returns and cancellations) and money collected (payments minus refunds), by period.',
    item: 'Units and revenue per item at list price, before any sale-level discount. Cost lets you see margin.',
    category: 'Units and revenue per item category, at list price.',
    class: 'Net sales by the class the student was in when they bought.',
    student: 'The 100 students who bought the most (net of returns).',
    cashier: 'Net sales by the person who recorded the sale.',
    method: 'Money collected by payment method, net of refunds.',
    stock: 'What moved in and out of stock in the period, and the current stock value.',
    'low-stock': 'Active items that are out of stock or at or below their reorder level.',
    outstanding: 'Sales that still owe money, oldest first.',
    requirements: 'How many students in each class have everything required, for the selected year.',
};

export default function ReportsIndex({ report, reports, filters, granularities, academicYears, data, can }) {
    const money = useMoney();
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);
    const dated = !['outstanding', 'low-stock', 'requirements'].includes(report);

    function go(params) {
        router.get('/supplies/reports', { report, from, to, granularity: filters.granularity, academic_year_id: filters.academic_year_id, ...params }, { preserveScroll: true });
    }

    const exportQuery = new URLSearchParams({ report, from, to, granularity: filters.granularity, ...(filters.academic_year_id ? { academic_year_id: filters.academic_year_id } : {}) }).toString();

    return (
        <AppLayout title="Reports" description="Sales, stock, outstanding balances and class requirements.">
            <Head title="Supplies reports" />
            <SuppliesNav current="reports" />

            <div className="grid gap-6 xl:grid-cols-[16rem_minmax(0,1fr)]">
                <nav aria-label="Reports" className="h-fit rounded-2xl border border-slate-200 bg-white p-2">
                    <ul>{reports.map((item) => <li key={item.key}><button type="button" aria-current={item.key === report ? 'page' : undefined} onClick={() => go({ report: item.key })} className={`w-full rounded-xl px-3 py-2.5 text-left text-sm font-medium ${item.key === report ? 'bg-teal-800 text-white' : 'text-slate-600 hover:bg-slate-100'}`}>{item.label}</button></li>)}</ul>
                </nav>

                <div className="min-w-0">
                    <section className="mb-6 rounded-2xl border border-slate-200 bg-white p-5">
                        <div className="flex flex-wrap items-end gap-3">
                            {dated && <><Field label="From"><input type="date" value={from} onChange={(event) => setFrom(event.target.value)} className={inputClass(false)} /></Field><Field label="To"><input type="date" value={to} onChange={(event) => setTo(event.target.value)} className={inputClass(false)} /></Field></>}
                            {report === 'sales' && <Field label="Group by"><select value={filters.granularity} onChange={(event) => go({ granularity: event.target.value })} className={inputClass(false)}>{granularities.map((value) => <option key={value} value={value}>{value[0].toUpperCase() + value.slice(1)}</option>)}</select></Field>}
                            {report === 'requirements' && <Field label="Academic year"><select value={filters.academic_year_id || ''} onChange={(event) => go({ academic_year_id: event.target.value })} className={inputClass(false)}>{academicYears.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}</select></Field>}
                            {dated && <button type="button" onClick={() => go({})} className="rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Apply</button>}
                            {can.export && <a href={`/supplies/reports/export?${exportQuery}`} className="ml-auto rounded-xl border border-teal-800 px-4 py-3 text-sm font-semibold text-teal-800 hover:bg-teal-50">Export CSV</a>}
                        </div>
                        <p className="mt-3 text-xs text-slate-500">{HINTS[report]}</p>
                    </section>

                    {report === 'sales' && <SalesTable data={data} money={money} />}
                    {['item', 'category', 'class', 'student', 'cashier', 'method'].includes(report) && <BreakdownTable rows={data.rows} by={report} money={money} />}
                    {['stock', 'low-stock'].includes(report) && <StockReport data={data} report={report} money={money} />}
                    {report === 'outstanding' && <OutstandingTable data={data} money={money} />}
                    {report === 'requirements' && <RequirementsTable rows={data.rows} year={filters.academic_year_id} />}
                </div>
            </div>
        </AppLayout>
    );
}

function Table({ head, children, empty }) {
    return empty ? <p className="rounded-2xl border border-slate-200 bg-white px-6 py-14 text-center text-sm text-slate-500">Nothing to show for these filters.</p> : (
        <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white"><div className="overflow-x-auto"><table className="w-full text-left text-sm"><thead className="bg-slate-50 text-xs text-slate-500"><tr>{head.map(([label, right], index) => <th key={`${label}-${index}`} className={`px-5 py-3 ${right ? 'text-right' : ''}`}>{label}</th>)}</tr></thead><tbody className="divide-y divide-slate-100">{children}</tbody></table></div></section>
    );
}

function SalesTable({ data, money }) {
    const max = Math.max(1, ...data.rows.map((row) => row.net));

    return (
        <>
            <div className="mb-4 grid gap-3 sm:grid-cols-4">{[['Sales', data.totals.sales], ['Net sales', money(data.totals.net)], ['Discounts given', money(data.totals.discounts)], ['Collected', money(data.totals.collected)]].map(([label, value]) => <article key={label} className="rounded-2xl border border-slate-200 bg-white p-4"><p className="text-xs text-slate-500">{label}</p><p className="mt-1 text-xl font-semibold text-slate-900">{value}</p></article>)}</div>
            <Table head={[['Period'], ['Sales', true], ['Net sales', true], ['Discounts', true], ['Collected', true], ['']]} empty={data.rows.length === 0}>
                {data.rows.map((row) => <tr key={row.period}><td className="px-5 py-3 font-medium text-slate-800">{row.period}</td><td className="px-5 py-3 text-right">{row.sales}</td><td className="px-5 py-3 text-right text-slate-900">{money(row.net)}</td><td className="px-5 py-3 text-right text-slate-600">{money(row.discounts)}</td><td className="px-5 py-3 text-right text-slate-900">{money(row.collected)}</td><td className="w-40 px-5 py-3"><div className="h-2 rounded-full bg-slate-100"><div className="h-2 rounded-full bg-teal-700" style={{ width: `${Math.round((row.net / max) * 100)}%` }} /></div></td></tr>)}
            </Table>
        </>
    );
}

function BreakdownTable({ rows, by, money }) {
    const countLabel = by === 'item' || by === 'category' ? 'Units' : by === 'method' ? 'Payments' : 'Sales';
    const showCost = by === 'item' || by === 'category';

    return (
        <Table head={[[by === 'method' ? 'Method' : by[0].toUpperCase() + by.slice(1)], [countLabel, true], ['Amount', true], ...(showCost ? [['Cost', true], ['Margin', true]] : [])]} empty={rows.length === 0}>
            {rows.map((row, index) => <tr key={`${row.label}-${index}`}><td className="px-5 py-3 text-slate-800">{row.label}{row.detail && <span className="block text-xs text-slate-500">{row.detail}</span>}</td><td className="px-5 py-3 text-right">{row.count}</td><td className="px-5 py-3 text-right text-slate-900">{money(row.amount)}</td>{showCost && <><td className="px-5 py-3 text-right text-slate-600">{money(row.cost)}</td><td className="px-5 py-3 text-right text-slate-900">{money(row.amount - row.cost)}</td></>}</tr>)}
        </Table>
    );
}

function StockReport({ data, report, money }) {
    const list = (title, items) => items.length === 0 ? null : (
        <section className="mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white"><div className="border-b border-slate-100 px-5 py-3"><h2 className="font-semibold text-slate-900">{title} ({items.length})</h2></div>
            <ul className="divide-y divide-slate-100 text-sm">{items.map((item) => <li key={item.id} className="flex flex-wrap items-center justify-between gap-3 px-5 py-3"><span><Link href={`/supplies/items/${item.id}`} className="font-medium text-teal-800 hover:text-teal-950">{item.name}</Link><span className="block text-xs text-slate-500">{item.sku} · {item.category} · reorder at {item.reorderLevel}</span></span><span className="font-semibold text-slate-900">{item.quantity} {item.unit}</span></li>)}</ul></section>
    );

    return (
        <>
            {report === 'stock' && (
                <>
                    <div className="mb-4 grid gap-3 sm:grid-cols-2">{[['Stock value at cost', money(data.valuation.cost)], ['Stock value at selling price', money(data.valuation.retail)]].map(([label, value]) => <article key={label} className="rounded-2xl border border-slate-200 bg-white p-4"><p className="text-xs text-slate-500">{label}</p><p className="mt-1 text-xl font-semibold text-slate-900">{value}</p></article>)}</div>
                    <Table head={[['Movement'], ['Count', true], ['Units', true]]} empty={false}>{data.summary.map((row) => <tr key={row.type}><td className="px-5 py-3 text-slate-800">{row.label}</td><td className="px-5 py-3 text-right">{row.movements}</td><td className="px-5 py-3 text-right text-slate-900">{row.units}</td></tr>)}</Table>
                </>
            )}
            {report === 'low-stock' && (
                <>
                    {list('Out of stock', data.out)}
                    {list('Low stock', data.low)}
                    {data.out.length === 0 && data.low.length === 0 && <p className="rounded-2xl border border-slate-200 bg-white px-6 py-14 text-center text-sm text-slate-500">Every active item is above its reorder level.</p>}
                </>
            )}
        </>
    );
}

function OutstandingTable({ data, money }) {
    return (
        <>
            <article className="mb-4 rounded-2xl border border-slate-200 bg-white p-4"><p className="text-xs text-slate-500">Total outstanding</p><p className="mt-1 text-xl font-semibold text-rose-700">{money(data.total)}</p></article>
            <Table head={[['Sale'], ['Student'], ['Class'], ['Sold'], ['Total', true], ['Paid', true], ['Balance', true]]} empty={data.rows.length === 0}>
                {data.rows.map((row) => <tr key={row.id}><td className="px-5 py-3"><Link href={`/supplies/sales/${row.id}`} className="font-medium text-teal-800 hover:text-teal-950">{row.saleNumber}</Link></td><td className="px-5 py-3 text-slate-700">{row.student}<span className="block text-xs text-slate-500">{row.admissionNumber}</span></td><td className="px-5 py-3 text-slate-600">{row.class || '—'}</td><td className="px-5 py-3 text-slate-600">{row.soldAt}</td><td className="px-5 py-3 text-right">{money(row.total)}</td><td className="px-5 py-3 text-right">{money(row.paid)}</td><td className="px-5 py-3 text-right font-semibold text-rose-700">{money(row.balance)}</td></tr>)}
            </Table>
        </>
    );
}

function RequirementsTable({ rows, year }) {
    return (
        <Table head={[['Class'], ['Requirements', true], ['Students', true], ['Complete', true], ['Progress']]} empty={rows.length === 0}>
            {rows.map((row) => <tr key={row.classId}><td className="px-5 py-3"><Link href={`/supplies/requirements?academic_year_id=${year}&class_id=${row.classId}`} className="font-medium text-teal-800 hover:text-teal-950">{row.class}</Link></td><td className="px-5 py-3 text-right">{row.requirements}</td><td className="px-5 py-3 text-right">{row.students}</td><td className="px-5 py-3 text-right text-slate-900">{row.complete} ({row.percent}%)</td><td className="w-48 px-5 py-3"><div className="h-2 rounded-full bg-slate-100"><div className="h-2 rounded-full bg-teal-700" style={{ width: `${row.percent}%` }} /></div></td></tr>)}
        </Table>
    );
}