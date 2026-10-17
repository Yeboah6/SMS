import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import { Field, inputClass } from '../Auth/Login';
import { STATUS_LABELS, dateLabel, humanize, statusStyle, useMoney } from '../../lib/fees';

export default function MyFees({ mode, invoices, students, selectedStudentId, academicYears, selectedAcademicYearId, summary }) {
    const money = useMoney();

    function filter(key, value) {
        const params = { academic_year_id: selectedAcademicYearId || 'all', student_id: selectedStudentId || '' };
        params[key] = value;
        Object.keys(params).forEach((name) => !params[name] && delete params[name]);
        router.get('/fees', params, { preserveScroll: true });
    }

    return (
        <AppLayout title="My fees" description="Review invoices, outstanding balances and payment receipts.">
            <Head title="My fees" />

            <section className="mb-6 grid gap-3 sm:grid-cols-3">
                {[['Billed', summary.billed], ['Paid', summary.paid], ['Outstanding', summary.outstanding]].map(([label, value]) => (
                    <article key={label} className="rounded-2xl border border-slate-200 bg-white p-5">
                        <p className="text-sm text-slate-500">{label}</p>
                        <p className="mt-2 text-2xl font-semibold tracking-tight text-slate-900">{money(value)}</p>
                    </article>
                ))}
            </section>

            <section className="mb-6 flex flex-wrap items-end justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-5">
                <div>
                    <h2 className="font-semibold text-slate-900">Invoices</h2>
                    <p className="mt-1 text-sm text-slate-500">{invoices.total} invoice{invoices.total === 1 ? '' : 's'} found</p>
                </div>
                <div className="flex flex-wrap items-end gap-3">
                    {mode === 'parent' && (
                        <Field label="Student">
                            <select value={selectedStudentId || ''} onChange={(event) => filter('student_id', event.target.value)} className={inputClass(false)}>
                                <option value="">All linked students</option>
                                {students.map((student) => <option key={student.id} value={student.id}>{student.name}</option>)}
                            </select>
                        </Field>
                    )}
                    <Field label="Academic year">
                        <select value={selectedAcademicYearId || 'all'} onChange={(event) => filter('academic_year_id', event.target.value)} className={inputClass(false)}>
                            <option value="all">All years</option>
                            {academicYears.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                        </select>
                    </Field>
                </div>
            </section>

            {invoices.data.length === 0 ? (
                <section className="rounded-2xl border border-slate-200 bg-white px-6 py-14 text-center">
                    <p className="font-medium text-slate-800">No invoices found</p>
                    <p className="mt-1 text-sm text-slate-500">Invoices issued for your account will appear here.</p>
                </section>
            ) : (
                <div className="grid gap-3">{invoices.data.map((invoice) => <InvoiceCard key={invoice.id} invoice={invoice} mode={mode} money={money} />)}</div>
            )}

            {invoices.links.length > 3 && (
                <nav aria-label="Invoice pages" className="mt-5 flex flex-wrap gap-2">
                    {invoices.links.map((link, index) => (
                        <Link key={`${link.label}-${index}`} href={link.url || '#'} preserveScroll className={`rounded-lg px-3 py-2 text-sm ${link.active ? 'bg-teal-700 text-white' : 'border border-slate-200 bg-white text-slate-700'} ${!link.url ? 'pointer-events-none opacity-50' : ''}`}>
                            {link.label.replace('&laquo;', '‹').replace('&raquo;', '›')}
                        </Link>
                    ))}
                </nav>
            )}
        </AppLayout>
    );
}

function InvoiceCard({ invoice, mode, money }) {
    const [expanded, setExpanded] = useState(false);

    return (
        <article className="rounded-2xl border border-slate-200 bg-white p-5">
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p className="font-semibold text-slate-900">{invoice.invoiceNumber}</p>
                    {mode !== 'student' && <p className="mt-1 text-sm text-slate-600">{invoice.studentName} · {invoice.admissionNumber}</p>}
                    <p className="mt-1 text-sm text-slate-500">{invoice.academicYear} · Issued {dateLabel(invoice.issuedAt)} · Due {dateLabel(invoice.dueDate)}</p>
                </div>
                <div className="flex items-center gap-2">
                    {invoice.overdue && <span className="rounded-full bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-800">Overdue</span>}
                    <span className={`rounded-full px-3 py-1 text-xs font-semibold ${statusStyle(invoice.status)}`}>{STATUS_LABELS[invoice.status]}</span>
                </div>
            </div>
            <div className="mt-4 grid gap-3 text-sm sm:grid-cols-3">
                <p className="text-slate-600">Billed <span className="font-semibold text-slate-900">{money(invoice.totalAmount)}</span></p>
                <p className="text-slate-600">Paid <span className="font-semibold text-slate-900">{money(invoice.paidAmount)}</span></p>
                <p className="text-slate-600">Balance <span className="font-semibold text-slate-900">{money(invoice.outstandingAmount)}</span></p>
            </div>
            <button type="button" onClick={() => setExpanded(!expanded)} className="mt-4 text-sm font-semibold text-teal-800 hover:text-teal-950">{expanded ? 'Hide details' : 'View invoice details'}</button>
            {expanded && (
                <div className="mt-4 grid gap-4 border-t border-slate-100 pt-4">
                    <div>
                        <h3 className="text-sm font-semibold text-slate-800">Invoice items</h3>
                        <ul className="mt-2 divide-y divide-slate-100 text-sm">
                            {invoice.items.map((item, index) => (
                                <li key={`${item.description}-${index}`} className="flex justify-between gap-3 py-2">
                                    <span className="text-slate-600">{item.description} · due {dateLabel(item.dueDate)}</span>
                                    <span className="font-medium text-slate-900">{money(item.amount)}</span>
                                </li>
                            ))}
                        </ul>
                    </div>
                    {invoice.payments.length > 0 && (
                        <div>
                            <h3 className="text-sm font-semibold text-slate-800">Payment receipts</h3>
                            <ul className="mt-2 divide-y divide-slate-100 text-sm">
                                {invoice.payments.map((payment) => (
                                    <li key={payment.id} className="flex flex-wrap justify-between gap-2 py-2">
                                        <Link href={`/payments/${payment.id}`} className="text-teal-800 hover:text-teal-950">{payment.receiptNumber} · {dateLabel(payment.date)} · {humanize(payment.method)}</Link>
                                        <span className="font-medium text-slate-900">{money(payment.amount)}</span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </div>
            )}
        </article>
    );
}