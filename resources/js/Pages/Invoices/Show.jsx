import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import PageAlerts from '../../Components/Exams/PageAlerts';
import FeesNav from '../../Components/Fees/FeesNav';
import PaymentForm from '../../Components/Fees/PaymentForm';
import { STATUS_LABELS, dateLabel, humanize, statusStyle, useMoney } from '../../lib/fees';

export default function InvoicesShow({ invoice, can, today }) {
    const money = useMoney();
    const percent = invoice.totalAmount > 0 ? Math.min(100, Math.round((invoice.paidAmount / invoice.totalAmount) * 100)) : 100;

    return (
        <AppLayout title={invoice.invoiceNumber} description={`${invoice.studentName} · ${invoice.admissionNumber} · ${invoice.academicYear}`}>
            <Head title={invoice.invoiceNumber} />
            <PageAlerts />
            <FeesNav current="invoices" />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <Link href="/invoices" className="text-sm font-semibold text-teal-800 hover:text-teal-950">Back to invoices</Link>
                <Link href={`/students/${invoice.studentId}`} className="text-sm font-semibold text-teal-800 hover:text-teal-950">Student profile</Link>
            </div>

            <section className="mb-6 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 className="font-semibold text-slate-900">Balance</h2>
                        <p className="mt-1 text-sm text-slate-500">Issued {dateLabel(invoice.issuedAt)} · Due {dateLabel(invoice.dueDate)}</p>
                    </div>
                    <div className="flex gap-2">
                        {invoice.overdue && <span className="rounded-full bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-800">Overdue</span>}
                        <span className={`rounded-full px-3 py-1 text-xs font-semibold ${statusStyle(invoice.status)}`}>{STATUS_LABELS[invoice.status]}</span>
                    </div>
                </div>
                <div className="mt-4 grid gap-3 text-sm sm:grid-cols-3">
                    <p className="text-slate-500">Billed <span className="block text-xl font-semibold text-slate-900">{money(invoice.totalAmount)}</span></p>
                    <p className="text-slate-500">Paid <span className="block text-xl font-semibold text-slate-900">{money(invoice.paidAmount)}</span></p>
                    <p className="text-slate-500">Outstanding <span className="block text-xl font-semibold text-slate-900">{money(invoice.outstandingAmount)}</span></p>
                </div>
                <div className="mt-4 h-2 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-valuenow={percent} aria-valuemin={0} aria-valuemax={100} aria-label="Share of the invoice paid">
                    <div className="h-full rounded-full bg-teal-700" style={{ width: `${percent}%` }} />
                </div>
            </section>

            {can.recordPayment && (
                <section className="mb-6 rounded-2xl border border-teal-200 bg-white p-5 sm:p-6">
                    <h2 className="font-semibold text-slate-900">Record a payment</h2>
                    <p className="mt-1 mb-4 text-sm text-slate-500">The amount cannot exceed the outstanding balance. A receipt is issued automatically.</p>
                    <PaymentForm key={`${invoice.id}-${invoice.outstandingAmount}`} invoice={invoice} today={today} />
                </section>
            )}

            <div className="grid gap-6 xl:grid-cols-2">
                <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <h2 className="font-semibold text-slate-900">Invoice items</h2>
                    <ul className="mt-3 divide-y divide-slate-100 text-sm">
                        {invoice.items.map((item, index) => (
                            <li key={`${item.description}-${index}`} className="flex justify-between gap-3 py-2.5">
                                <span className="text-slate-700">{item.description}<span className="block text-xs text-slate-500">Due {dateLabel(item.dueDate)}</span></span>
                                <span className="font-medium text-slate-900">{money(item.amount)}</span>
                            </li>
                        ))}
                        <li className="flex justify-between gap-3 py-2.5 font-semibold text-slate-900"><span>Total</span><span>{money(invoice.totalAmount)}</span></li>
                    </ul>
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <h2 className="font-semibold text-slate-900">Payments</h2>
                    {invoice.payments.length === 0 ? (
                        <p className="mt-3 rounded-xl bg-slate-50 px-3 py-4 text-sm text-slate-500">No payments recorded yet.</p>
                    ) : (
                        <ul className="mt-3 divide-y divide-slate-100 text-sm">
                            {invoice.payments.map((payment) => (
                                <li key={payment.id} className="flex flex-wrap justify-between gap-2 py-2.5">
                                    <span>
                                        <Link href={`/payments/${payment.id}`} className="font-medium text-teal-800 hover:text-teal-950">{payment.receiptNumber}</Link>
                                        <span className="block text-xs text-slate-500">{dateLabel(payment.date)} · {humanize(payment.method)}{payment.reference ? ` · ${payment.reference}` : ''}</span>
                                    </span>
                                    <span className="font-medium text-slate-900">{money(payment.amount)}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                    <p className="mt-4 text-xs text-slate-500">Payments cannot be edited or deleted, so receipts always match the books.</p>
                </section>
            </div>
        </AppLayout>
    );
}