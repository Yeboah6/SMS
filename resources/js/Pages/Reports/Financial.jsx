import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { inputClass } from '../Auth/Login';

export default function FinancialReport({
    academicYears,
    selectedAcademicYearId,
    selectedClassId,
    classes,
    fromDate,
    toDate,
    summary,
    payments,
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
    if (fromDate) {
        exportParams.set('from_date', fromDate);
    }
    if (toDate) {
        exportParams.set('to_date', toDate);
    }

    return (
        <AppLayout
            title="Fee collection report"
            description="Review collected payments by academic year, class, and payment date."
        >
            <Head title="Fee collection report" />

            <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <form method="GET" action="/reports/financial" className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
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
                        Payment date from
                        <input type="date" name="from_date" defaultValue={fromDate} className={inputClass(false)} />
                    </label>
                    <label className="grid gap-1.5 text-sm font-medium text-slate-700">
                        Payment date to
                        <input type="date" name="to_date" defaultValue={toDate} className={inputClass(false)} />
                    </label>
                    <div className="flex items-center gap-2 sm:col-span-2 xl:col-span-4">
                        <button type="submit" className="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-800">
                            Apply filters
                        </button>
                        {canExport && selectedAcademicYearId && (
                            <a href={`/reports/financial/export?${exportParams.toString()}`} className="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                Download CSV
                            </a>
                        )}
                    </div>
                </form>
            </section>

            <section aria-label="Fee collection totals" className="mt-6 grid gap-3 sm:grid-cols-2">
                <article className="rounded-2xl border border-slate-200 bg-white p-4">
                    <p className="text-sm font-medium text-slate-500">Payments collected</p>
                    <p className="mt-2 text-2xl font-bold tracking-tight text-slate-950">{summary.paymentCount.toLocaleString()}</p>
                </article>
                <article className="rounded-2xl border border-slate-200 bg-white p-4">
                    <p className="text-sm font-medium text-slate-500">Total collected</p>
                    <p className="mt-2 text-2xl font-bold tracking-tight text-emerald-700">{amountFormat.format(summary.collected)}</p>
                </article>
            </section>

            <section className="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div className="flex flex-col gap-1 border-b border-slate-100 px-5 py-4 sm:px-6">
                    <h2 className="font-semibold text-slate-900">Payment transactions</h2>
                    <p className="text-sm text-slate-500">{payments.total.toLocaleString()} matching receipts</p>
                </div>
                {payments.data.length === 0 ? (
                    <div className="px-6 py-12 text-center">
                        <p className="font-medium text-slate-800">No payments found</p>
                        <p className="mt-1 text-sm text-slate-500">Try another date range or class, or record a payment first.</p>
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-100 text-left text-sm">
                            <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th className="px-5 py-3 font-semibold">Receipt</th>
                                    <th className="px-5 py-3 font-semibold">Date</th>
                                    <th className="px-5 py-3 font-semibold">Student</th>
                                    <th className="px-5 py-3 font-semibold">Class</th>
                                    <th className="px-5 py-3 font-semibold">Invoice</th>
                                    <th className="px-5 py-3 font-semibold">Method</th>
                                    <th className="px-5 py-3 text-right font-semibold">Amount</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {payments.data.map((payment) => (
                                    <tr key={payment.id} className="hover:bg-slate-50/70">
                                        <td className="whitespace-nowrap px-5 py-3.5 font-medium text-slate-900">{payment.receiptNumber}</td>
                                        <td className="whitespace-nowrap px-5 py-3.5 text-slate-600">{payment.paymentDate}</td>
                                        <td className="whitespace-nowrap px-5 py-3.5">
                                            <p className="font-medium text-slate-900">{payment.studentName}</p>
                                            <p className="mt-0.5 text-xs text-slate-500">{payment.admissionNumber}</p>
                                        </td>
                                        <td className="whitespace-nowrap px-5 py-3.5 text-slate-600">{payment.className}</td>
                                        <td className="whitespace-nowrap px-5 py-3.5 text-slate-600">{payment.invoiceNumber}</td>
                                        <td className="whitespace-nowrap px-5 py-3.5 capitalize text-slate-600">{payment.paymentMethod.replaceAll('_', ' ')}</td>
                                        <td className="whitespace-nowrap px-5 py-3.5 text-right font-semibold text-slate-900">{amountFormat.format(payment.amount)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                {payments.links.length > 3 && (
                    <nav aria-label="Fee collection report pages" className="flex flex-wrap justify-between gap-2 border-t border-slate-100 px-5 py-4">
                        {payments.links.map((link) => (
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
