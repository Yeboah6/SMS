import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import PageAlerts from '../../Components/Exams/PageAlerts';
import FeesNav from '../../Components/Fees/FeesNav';
import YearSelect from '../../Components/Fees/YearSelect';
import { STATUS_LABELS, dateLabel, statusStyle, useMoney } from '../../lib/fees';

const TABS = [['', 'All', 'all'], ['unpaid', 'Unpaid', 'unpaid'], ['partially_paid', 'Partially paid', 'partially_paid'], ['paid', 'Paid', 'paid'], ['overdue', 'Overdue', 'overdue']];

export default function InvoicesIndex({ invoices, academicYears, selectedAcademicYearId, filters, counts, summary, can }) {
    const money = useMoney();
    const [search, setSearch] = useState(filters.search || '');

    function visit(overrides = {}) {
        const params = { academic_year_id: selectedAcademicYearId, search, status: filters.status, ...overrides };
        Object.keys(params).forEach((key) => !params[key] && delete params[key]);
        router.get('/invoices', params, { preserveState: true, replace: true });
    }

    return (
        <AppLayout title="Invoices" description="Step 3: every student's bill for the year, and how much of it is paid.">
            <Head title="Invoices" />
            <PageAlerts />
            <FeesNav current="invoices" yearId={selectedAcademicYearId} />

            <section className="mb-6 flex flex-wrap items-end justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-5">
                <div className="grid gap-x-8 gap-y-1 text-sm sm:grid-cols-3">
                    <p className="text-slate-500">Billed <span className="block text-lg font-semibold text-slate-900">{money(summary.billed)}</span></p>
                    <p className="text-slate-500">Collected <span className="block text-lg font-semibold text-slate-900">{money(summary.paid)}</span></p>
                    <p className="text-slate-500">Outstanding <span className="block text-lg font-semibold text-slate-900">{money(summary.outstanding)}</span></p>
                </div>
                <div className="flex flex-wrap items-end gap-3">
                    {can.export && <a href={`/invoices/export?academic_year_id=${selectedAcademicYearId}`} className="rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Export</a>}
                    {can.generate && <Link href={`/invoices/generate?academic_year_id=${selectedAcademicYearId}`} className="rounded-xl bg-teal-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-900">Generate invoices</Link>}
                    <YearSelect years={academicYears} value={selectedAcademicYearId} onChange={(value) => visit({ academic_year_id: value, search: '', status: '' })} />
                </div>
            </section>

            <nav aria-label="Invoice status" className="mb-5 flex gap-2 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-2">
                {TABS.map(([value, label, key]) => (
                    <button key={key} type="button" aria-pressed={filters.status === value} onClick={() => visit({ status: value })} className={`shrink-0 rounded-xl px-4 py-2.5 text-sm font-semibold ${filters.status === value ? 'bg-teal-800 text-white' : 'text-slate-600 hover:bg-slate-100'}`}>
                        {label} <span className={filters.status === value ? 'text-teal-100' : 'text-slate-400'}>{counts[key]}</span>
                    </button>
                ))}
            </nav>

            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                    <h2 className="font-semibold text-slate-900">{invoices.total.toLocaleString()} invoice{invoices.total === 1 ? '' : 's'}</h2>
                    <form onSubmit={(event) => { event.preventDefault(); visit(); }} className="flex gap-2">
                        <input type="search" aria-label="Search invoices" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Invoice, student or admission no." className="w-60 rounded-xl border border-slate-200 px-3 py-2 text-sm" />
                        <button className="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Search</button>
                    </form>
                </div>

                {invoices.data.length === 0 ? (
                    <div className="px-6 py-14 text-center">
                        <p className="font-medium text-slate-800">No invoices found</p>
                        <p className="mt-1 text-sm text-slate-500">{counts.all === 0 ? 'Generate invoices once the class fee structures are set.' : 'Try a different status or search.'}</p>
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[860px] text-left text-sm">
                            <thead className="bg-slate-50 text-xs text-slate-500">
                                <tr><th className="px-5 py-3">Invoice</th><th className="px-5 py-3">Student</th><th className="px-5 py-3">Due</th><th className="px-5 py-3 text-right">Billed</th><th className="px-5 py-3 text-right">Balance</th><th className="px-5 py-3">Status</th></tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {invoices.data.map((invoice) => (
                                    <tr key={invoice.id} className="hover:bg-slate-50/70">
                                        <td className="px-5 py-3.5"><Link href={`/invoices/${invoice.id}`} className="font-medium text-teal-800 hover:text-teal-950">{invoice.invoiceNumber}</Link></td>
                                        <td className="px-5 py-3.5 text-slate-700">{invoice.studentName}<span className="block text-xs text-slate-500">{invoice.admissionNumber}</span></td>
                                        <td className="px-5 py-3.5 text-slate-600">{dateLabel(invoice.dueDate)}</td>
                                        <td className="px-5 py-3.5 text-right text-slate-700">{money(invoice.totalAmount)}</td>
                                        <td className="px-5 py-3.5 text-right font-medium text-slate-900">{money(invoice.outstandingAmount)}</td>
                                        <td className="px-5 py-3.5">
                                            <div className="flex flex-wrap gap-1.5">
                                                <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${statusStyle(invoice.status)}`}>{STATUS_LABELS[invoice.status]}</span>
                                                {invoice.overdue && <span className="rounded-full bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-800">Overdue</span>}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {invoices.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-slate-100 px-5 py-3">
                        <button type="button" disabled={invoices.current_page <= 1} onClick={() => visit({ page: invoices.current_page - 1 })} className="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 disabled:opacity-40">Previous</button>
                        <span className="text-xs text-slate-500">Page {invoices.current_page} of {invoices.last_page}</span>
                        <button type="button" disabled={invoices.current_page >= invoices.last_page} onClick={() => visit({ page: invoices.current_page + 1 })} className="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 disabled:opacity-40">Next</button>
                    </div>
                )}
            </section>
        </AppLayout>
    );
}