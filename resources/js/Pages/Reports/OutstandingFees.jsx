import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { inputClass } from '../Auth/Login';

export default function OutstandingFeesReport({
    academicYears,
    selectedAcademicYearId,
    selectedClassId,
    classes,
    asOfDate,
    summary,
    invoices,
    canExport,
}) {
    const { feeSettings } = usePage().props;
    const amountFormat = new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: feeSettings.currency_code,
    });
    const exportParams = new URLSearchParams();
    if (selectedAcademicYearId) {
        exportParams.set('academic_year_id', selectedAcademicYearId);
    }
    if (selectedClassId) {
        exportParams.set('class_id', selectedClassId);
    }
    if (asOfDate) {
        exportParams.set('as_of_date', asOfDate);
    }

    return (
        <AppLayout
            title="Outstanding fees report"
            description="Review unpaid invoice balances and overdue amounts as of a selected date."
        >
            <Head title="Outstanding fees report" />

            <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <form method="GET" action="/reports/financial/outstanding" className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <label className="grid gap-1.5 text-sm font-medium text-slate-700">
                        Academic year
                        <select name="academic_year_id" defaultValue={selectedAcademicYearId || ''} className={inputClass(false)}>
                            <option value="">Active academic year</option>
                            {academicYears.map((year) => (
                                <option key={year.id} value={year.id}>{year.name}</option>
                            ))}
                        </select>
                    </label>
                    <label className="grid gap-1.5 text-sm font-medium text-slate-700">
                        Class
                        <select name="class_id" defaultValue={selectedClassId || ''} className={inputClass(false)}>
                            <option value="">All classes</option>
                            {classes.map((schoolClass) => (
                                <option key={schoolClass.id} value={schoolClass.id}>{schoolClass.name}</option>
                            ))}
                        </select>
                    </label>
                    <label className="grid gap-1.5 text-sm font-medium text-slate-700">
                        Balance as of
                        <input type="date" name="as_of_date" defaultValue={asOfDate} className={inputClass(false)} required />
                    </label>
                    <div className="flex items-center gap-2 sm:col-span-2 xl:col-span-3">
                        <button type="submit" className="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-800">
                            Apply filters
                        </button>
                        {canExport && selectedAcademicYearId && (
                            <a href={`/reports/financial/outstanding/export?${exportParams.toString()}`} className="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                Download CSV
                            </a>
                        )}
                    </div>
                </form>
            </section>

            <section aria-label="Outstanding fee totals" className="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <article className="rounded-2xl border border-slate-200 bg-white p-4">
                    <p className="text-sm font-medium text-slate-500">Outstanding invoices</p>
                    <p className="mt-2 text-2xl font-bold tracking-tight text-slate-950">{summary.invoiceCount.toLocaleString()}</p>
                </article>
                <article className="rounded-2xl border border-slate-200 bg-white p-4">
                    <p className="text-sm font-medium text-slate-500">Outstanding balance</p>
                    <p className="mt-2 text-2xl font-bold tracking-tight text-slate-950">{amountFormat.format(summary.outstanding)}</p>
                </article>
                <article className="rounded-2xl border border-slate-200 bg-white p-4">
                    <p className="text-sm font-medium text-slate-500">Overdue invoices</p>
                    <p className="mt-2 text-2xl font-bold tracking-tight text-rose-700">{summary.overdueInvoiceCount.toLocaleString()}</p>
                </article>
                <article className="rounded-2xl border border-slate-200 bg-white p-4">
                    <p className="text-sm font-medium text-slate-500">Overdue balance</p>
                    <p className="mt-2 text-2xl font-bold tracking-tight text-rose-700">{amountFormat.format(summary.overdue)}</p>
                </article>
            </section>

            <section className="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div className="flex flex-col gap-1 border-b border-slate-100 px-5 py-4 sm:px-6">
                    <h2 className="font-semibold text-slate-900">Unpaid invoice balances</h2>
                    <p className="text-sm text-slate-500">{invoices.total.toLocaleString()} invoices outstanding as of {asOfDate}</p>
                    <p className="text-xs text-slate-500">Payments dated after the selected date are excluded. An invoice becomes overdue the day after its due date.</p>
                </div>
                {invoices.data.length === 0 ? (
                    <div className="px-6 py-12 text-center">
                        <p className="font-medium text-slate-800">No outstanding invoices found</p>
                        <p className="mt-1 text-sm text-slate-500">Choose another academic year or date, or generate invoices first.</p>
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-100 text-left text-sm">
                            <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th className="px-5 py-3 font-semibold">Invoice</th>
                                    <th className="px-5 py-3 font-semibold">Student</th>
                                    <th className="px-5 py-3 font-semibold">Class</th>
                                    <th className="px-5 py-3 font-semibold">Due date</th>
                                    <th className="px-5 py-3 text-right font-semibold">Total</th>
                                    <th className="px-5 py-3 text-right font-semibold">Paid</th>
                                    <th className="px-5 py-3 text-right font-semibold">Balance</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {invoices.data.map((invoice) => (
                                    <tr key={invoice.id} className="hover:bg-slate-50/70">
                                        <td className="whitespace-nowrap px-5 py-3.5 font-medium text-slate-900">{invoice.invoiceNumber}</td>
                                        <td className="whitespace-nowrap px-5 py-3.5">
                                            <p className="font-medium text-slate-900">{invoice.studentName}</p>
                                            <p className="mt-0.5 text-xs text-slate-500">{invoice.admissionNumber}</p>
                                        </td>
                                        <td className="whitespace-nowrap px-5 py-3.5 text-slate-600">{invoice.className}</td>
                                        <td className="whitespace-nowrap px-5 py-3.5">
                                            <p className="text-slate-700">{invoice.dueDate}</p>
                                            {invoice.daysOverdue > 0 && (
                                                <p className="mt-0.5 text-xs font-medium text-rose-700">{invoice.daysOverdue} days overdue</p>
                                            )}
                                        </td>
                                        <td className="whitespace-nowrap px-5 py-3.5 text-right text-slate-600">{amountFormat.format(invoice.totalAmount)}</td>
                                        <td className="whitespace-nowrap px-5 py-3.5 text-right text-slate-600">{amountFormat.format(invoice.paidAmount)}</td>
                                        <td className="whitespace-nowrap px-5 py-3.5 text-right font-semibold text-rose-700">{amountFormat.format(invoice.outstandingAmount)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                {invoices.links.length > 3 && (
                    <nav aria-label="Outstanding fees report pages" className="flex flex-wrap justify-between gap-2 border-t border-slate-100 px-5 py-4">
                        {invoices.links.map((link) => (
                            <button
                                key={link.label}
                                type="button"
                                disabled={!link.url || link.active}
                                onClick={() => link.url && router.get(link.url, {}, { preserveScroll: true })}
                                aria-current={link.active ? 'page' : undefined}
                                className={`rounded-lg px-3 py-2 text-sm ${link.active ? 'bg-teal-50 font-semibold text-teal-800' : 'text-slate-600'} disabled:opacity-40`}
                            >
                                {link.label.replace('&laquo;', '‹').replace('&raquo;', '›')}
                            </button>
                        ))}
                    </nav>
                )}
            </section>
        </AppLayout>
    );
}
