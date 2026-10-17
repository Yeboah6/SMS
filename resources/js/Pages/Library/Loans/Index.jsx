import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../../Layouts/AppLayout';
import PageAlerts from '../../../Components/Exams/PageAlerts';
import LibraryNav from '../../../Components/Books/LibraryNav';
import { Field, inputClass } from '../../Auth/Login';
import { LOAN_LABELS, dateLabel, loanTone } from '../../../lib/library';

const TABS = [['', 'All', 'all'], ['issued', 'On loan', 'issued'], ['overdue', 'Overdue', 'overdue'], ['returned', 'Returned', 'returned']];

export default function LoansIndex({ loans, filters, counts, can }) {
    const [search, setSearch] = useState(filters.search || '');

    function visit(overrides = {}) {
        const params = { search, status: filters.status, ...overrides };
        Object.keys(params).forEach((key) => !params[key] && delete params[key]);
        router.get('/library/loans', params, { preserveState: true, replace: true });
    }

    return (
        <AppLayout title="Loans" description="Who has borrowed what. Return books and extend due dates here; issue them from a book's page.">
            <Head title="Loans" />
            <PageAlerts />
            <LibraryNav current="loans" />

            <nav aria-label="Loan status" className="mb-5 flex gap-2 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-2">
                {TABS.map(([value, label, key]) => (
                    <button key={key} type="button" aria-pressed={filters.status === value} onClick={() => visit({ status: value })} className={`shrink-0 rounded-xl px-4 py-2.5 text-sm font-semibold ${filters.status === value ? 'bg-teal-800 text-white' : 'text-slate-600 hover:bg-slate-100'}`}>
                        {label} <span className={filters.status === value ? 'text-teal-100' : 'text-slate-400'}>{counts[key]}</span>
                    </button>
                ))}
            </nav>

            <section className="mb-6">
                <form onSubmit={(event) => { event.preventDefault(); visit(); }} className="flex flex-wrap gap-2">
                    <input type="search" aria-label="Search loans" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Book, accession number, student" className="w-72 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm" />
                    <button className="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Search</button>
                    <Link href="/library/books" className="rounded-xl bg-teal-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-900">Issue from inventory</Link>
                </form>
            </section>

            {loans.data.length === 0 ? (
                <p className="rounded-2xl border border-slate-200 bg-white px-6 py-14 text-center text-sm text-slate-500">No loans found.</p>
            ) : (
                <div className="grid gap-3">
                    {loans.data.map((loan) => (
                        <article key={loan.id} className="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 lg:grid-cols-[1fr_auto] lg:items-center">
                            <div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <Link href={`/library/books/${loan.bookId}`} className="font-semibold text-slate-900 hover:text-teal-800">{loan.bookTitle}</Link>
                                    <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${loanTone(loan.status)}`}>{LOAN_LABELS[loan.status]}</span>
                                </div>
                                <p className="mt-1 text-sm text-slate-600">{loan.author} · {loan.accessionNumber}</p>
                                <p className="mt-1 text-sm text-slate-600">{loan.studentName} · {loan.admissionNumber}</p>
                                <p className="mt-1 text-xs text-slate-500">Issued {dateLabel(loan.issueDate)} · Due {dateLabel(loan.dueDate)}{loan.returnedAt ? ` · Returned ${dateLabel(loan.returnedAt)}` : ''}{loan.renewalCount ? ` · Renewed ${loan.renewalCount} time${loan.renewalCount === 1 ? '' : 's'}` : ''}</p>
                            </div>
                            {loan.status !== 'returned' && (
                                <div className="flex flex-wrap items-end gap-2">
                                    {can.renew && loan.status !== 'overdue' && <RenewForm key={`${loan.id}-${loan.dueDate}`} loan={loan} />}
                                    {can.return && <button type="button" onClick={() => router.post(`/library/loans/${loan.id}/return`, {}, { preserveScroll: true })} className="rounded-lg bg-teal-700 px-3 py-2.5 text-sm font-semibold text-white hover:bg-teal-800">Return book</button>}
                                </div>
                            )}
                        </article>
                    ))}
                </div>
            )}

            {loans.last_page > 1 && (
                <div className="mt-5 flex items-center justify-between rounded-xl border border-slate-200 bg-white px-4 py-3">
                    <button type="button" disabled={loans.current_page <= 1} onClick={() => visit({ page: loans.current_page - 1 })} className="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 disabled:opacity-40">Previous</button>
                    <span className="text-xs text-slate-500">Page {loans.current_page} of {loans.last_page}</span>
                    <button type="button" disabled={loans.current_page >= loans.last_page} onClick={() => visit({ page: loans.current_page + 1 })} className="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 disabled:opacity-40">Next</button>
                </div>
            )}
        </AppLayout>
    );
}

function RenewForm({ loan }) {
    const form = useForm({ due_date: loan.dueDate });

    function renew(event) {
        event.preventDefault();
        form.post(`/library/loans/${loan.id}/renew`, { preserveScroll: true });
    }

    return (
        <form onSubmit={renew} className="flex flex-wrap items-end gap-2">
            <Field label="New due date" error={form.errors.due_date}>
                <input type="date" value={form.data.due_date} onChange={(event) => form.setData('due_date', event.target.value)} className={inputClass(form.errors.due_date)} required />
            </Field>
            <button type="submit" disabled={form.processing} className="rounded-lg border border-teal-200 px-3 py-2.5 text-sm font-semibold text-teal-800 hover:bg-teal-50 disabled:opacity-50">{form.processing ? 'Saving…' : 'Renew'}</button>
        </form>
    );
}