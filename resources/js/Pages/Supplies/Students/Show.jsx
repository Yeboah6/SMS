import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import SuppliesNav from '../../../Components/Supplies/SuppliesNav';
import Pill from '../../../Components/Supplies/Pill';
import Pager from '../../../Components/Supplies/Pager';
import { PAYMENT_STATUS, REQUIREMENT_STATUS, SALE_STATUS, fmtDateTime, useMoney } from '../../../lib/supplies';

export default function StudentSuppliesShow({ student, sales, totals, requirements, can }) {
    const money = useMoney();

    return (
        <AppLayout title={student.name} description={`${student.admissionNumber}${student.className ? ` · ${student.className}` : ''} · Books & supplies`}>
            <Head title={`${student.name} · supplies`} />
            <SuppliesNav current="sales" />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <Link href="/supplies/sales" className="text-sm font-semibold text-teal-800 hover:text-teal-950">Back to sales</Link>
                <div className="flex items-center gap-4 text-sm font-semibold">
                    <Link href={`/students/${student.id}`} className="text-teal-800 hover:text-teal-950">Student profile</Link>
                    {can.sell && <Link href={`/supplies/sales/create?student=${student.id}`} className="rounded-lg bg-teal-800 px-3 py-2 text-white hover:bg-teal-900">New sale</Link>}
                </div>
            </div>

            <section className="mb-6 grid gap-3 sm:grid-cols-3">
                {[['Sales', totals.sales, null], ['Total bought (net of returns)', money(totals.net), null], ['Outstanding', money(totals.outstanding), totals.outstanding > 0 ? 'text-rose-700' : '']].map(([label, value, tone]) => <article key={label} className="rounded-2xl border border-slate-200 bg-white p-5"><p className="text-sm text-slate-500">{label}</p><p className={`mt-2 text-2xl font-semibold tracking-tight ${tone || 'text-slate-900'}`}>{value}</p></article>)}
            </section>

            {requirements && (
                <section className="mb-6 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <div className="flex flex-wrap items-center justify-between gap-3"><h2 className="font-semibold text-slate-900">Class requirements this year</h2><span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${requirements.complete ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-800'}`}>{requirements.complete ? 'All required items covered' : 'Items still needed'}</span></div>
                    {requirements.requirements.length === 0 ? <p className="mt-4 rounded-xl bg-slate-50 px-3 py-4 text-sm text-slate-500">No requirements are set for this class.</p> : (
                        <div className="mt-4 overflow-x-auto">
                            <table className="w-full min-w-[620px] text-left text-sm">
                                <thead className="border-b border-slate-100 text-xs text-slate-500"><tr><th className="py-3 pr-4">Item</th><th className="py-3 pr-4">Needed</th><th className="py-3 pr-4">Bought</th><th className="py-3 pr-4">Still needed</th><th className="py-3">Status</th></tr></thead>
                                <tbody className="divide-y divide-slate-100">
                                    {requirements.requirements.map((requirement) => {
                                        const line = requirements.lines[requirement.id];
                                        return <tr key={requirement.id}><td className="py-3 pr-4 text-slate-800">{requirement.itemName}{!requirement.isRequired && <span className="ml-2 text-xs text-slate-500">recommended</span>}{requirement.termName && <span className="block text-xs text-slate-500">{requirement.termName}</span>}</td><td className="py-3 pr-4">{requirement.quantity}</td><td className="py-3 pr-4">{line.purchased}</td><td className="py-3 pr-4">{line.outstanding}</td><td className="py-3"><Pill map={REQUIREMENT_STATUS} value={line.status} /></td></tr>;
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            )}

            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div className="border-b border-slate-100 px-5 py-4"><h2 className="font-semibold text-slate-900">Purchase history</h2></div>
                {sales.data.length === 0 ? <p className="px-6 py-12 text-center text-sm text-slate-500">No purchases yet.</p> : (
                    <ul className="divide-y divide-slate-100 text-sm">
                        {sales.data.map((sale) => (
                            <li key={sale.id} className="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5">
                                <span><Link href={`/supplies/sales/${sale.id}`} className="font-medium text-teal-800 hover:text-teal-950">{sale.saleNumber}</Link><span className="block text-xs text-slate-500">{fmtDateTime(sale.soldAt)} · {sale.academicYear}</span></span>
                                <span className="flex flex-wrap items-center gap-3"><span className="font-semibold text-slate-900">{money(sale.netDue)}</span>{sale.balance > 0 && <span className="text-xs font-semibold text-rose-700">{money(sale.balance)} owing</span>}<Pill map={PAYMENT_STATUS} value={sale.paymentStatus} /><Pill map={SALE_STATUS} value={sale.status} /></span>
                            </li>
                        ))}
                    </ul>
                )}
                <Pager links={sales.links} />
            </section>
        </AppLayout>
    );
}