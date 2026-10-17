import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import PageAlerts from '../../Components/Exams/PageAlerts';
import FeesNav from '../../Components/Fees/FeesNav';
import YearSelect from '../../Components/Fees/YearSelect';
import { Field, inputClass } from '../Auth/Login';
import { dateLabel, humanize, useMoney } from '../../lib/fees';

export default function PaymentsIndex({ payments, academicYears, selectedAcademicYearId, filters, methods, totalCollected, can }) {
    const money = useMoney();
    const [search, setSearch] = useState(filters.search || '');
    const [from, setFrom] = useState(filters.from || '');
    const [to, setTo] = useState(filters.to || '');

    function visit(overrides = {}) {
        const params = { academic_year_id: selectedAcademicYearId || 'all', search, method: filters.method, from, to, ...overrides };
        Object.keys(params).forEach((key) => !params[key] && delete params[key]);
        router.get('/payments', params, { preserveState: true, replace: true });
    }

    return (
        <AppLayout title="Payments" description="Step 4: every payment received, with its receipt.">
            <Head title="Payments" />
            <PageAlerts />
            <FeesNav current="payments" yearId={selectedAcademicYearId} />

            <section className="mb-6 flex flex-wrap items-end justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-5">
                <p className="text-sm text-slate-500">Collected in this view <span className="block text-2xl font-semibold tracking-tight text-slate-900">{money(totalCollected)}</span></p>
                <div className="flex flex-wrap items-end gap-3">
                    {can.export && <a href={`/payments/export${selectedAcademicYearId ? `?academic_year_id=${selectedAcademicYearId}` : ''}`} className="rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Export</a>}
                    <YearSelect years={academicYears} value={selectedAcademicYearId} allowAll onChange={(value) => visit({ academic_year_id: value })} />
                </div>
            </section>

            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <form onSubmit={(event) => { event.preventDefault(); visit(); }} className="grid gap-3 border-b border-slate-100 px-5 py-4 sm:grid-cols-2 xl:grid-cols-[1.4fr_1fr_1fr_1fr_auto] xl:items-end">
                    <Field label="Search"><input type="search" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Receipt, invoice, student" className={inputClass(false)} /></Field>
                    <Field label="Method">
                        <select value={filters.method} onChange={(event) => visit({ method: event.target.value })} className={inputClass(false)}>
                            <option value="">All methods</option>
                            {methods.map((method) => <option key={method} value={method}>{humanize(method)}</option>)}
                        </select>
                    </Field>
                    <Field label="From"><input type="date" value={from} onChange={(event) => setFrom(event.target.value)} className={inputClass(false)} /></Field>
                    <Field label="To"><input type="date" value={to} onChange={(event) => setTo(event.target.value)} className={inputClass(false)} /></Field>
                    <button className="rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Apply</button>
                </form>

                {payments.data.length === 0 ? (
                    <p className="px-6 py-14 text-center text-sm text-slate-500">No payments match. Payments are recorded on an invoice.</p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[820px] text-left text-sm">
                            <thead className="bg-slate-50 text-xs text-slate-500">
                                <tr><th className="px-5 py-3">Receipt</th><th className="px-5 py-3">Student</th><th className="px-5 py-3">Invoice</th><th className="px-5 py-3">Date</th><th className="px-5 py-3">Method</th><th className="px-5 py-3 text-right">Amount</th></tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {payments.data.map((payment) => (
                                    <tr key={payment.id} className="hover:bg-slate-50/70">
                                        <td className="px-5 py-3.5"><Link href={`/payments/${payment.id}`} className="font-medium text-teal-800 hover:text-teal-950">{payment.receiptNumber}</Link></td>
                                        <td className="px-5 py-3.5 text-slate-700">{payment.studentName}<span className="block text-xs text-slate-500">{payment.admissionNumber}</span></td>
                                        <td className="px-5 py-3.5"><Link href={`/invoices/${payment.invoiceId}`} className="text-slate-600 hover:text-teal-800">{payment.invoiceNumber}</Link></td>
                                        <td className="px-5 py-3.5 text-slate-600">{dateLabel(payment.date)}</td>
                                        <td className="px-5 py-3.5 text-slate-600 capitalize">{humanize(payment.method)}{payment.reference && <span className="block text-xs normal-case text-slate-500">{payment.reference}</span>}</td>
                                        <td className="px-5 py-3.5 text-right font-medium text-slate-900">{money(payment.amount)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {payments.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-slate-100 px-5 py-3">
                        <button type="button" disabled={payments.current_page <= 1} onClick={() => visit({ page: payments.current_page - 1 })} className="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 disabled:opacity-40">Previous</button>
                        <span className="text-xs text-slate-500">Page {payments.current_page} of {payments.last_page}</span>
                        <button type="button" disabled={payments.current_page >= payments.last_page} onClick={() => visit({ page: payments.current_page + 1 })} className="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 disabled:opacity-40">Next</button>
                    </div>
                )}
            </section>
        </AppLayout>
    );
}