import { Head, Link, router, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import PageAlerts from '../../../Components/Exams/PageAlerts';
import LibraryNav from '../../../Components/Books/LibraryNav';
import CopyRow from '../../../Components/Books/CopyRow';
import { Field, inputClass } from '../../Auth/Login';
import { LOAN_LABELS, dateLabel, loanTone } from '../../../lib/library';

export default function BooksShow({ book, stock, copies, history, can }) {
    function deleteBook() {
        if (window.confirm(`Delete ${book.title} and all its copies?`)) {
            router.delete(`/library/books/${book.id}`);
        }
    }

    return (
        <AppLayout title={book.title} description={`${book.author} · ${book.category}`}>
            <Head title={book.title} />
            <PageAlerts />
            <LibraryNav current="inventory" />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <Link href="/library/books" className="text-sm font-semibold text-teal-800 hover:text-teal-950">Back to inventory</Link>
                <div className="flex items-center gap-4 text-sm font-semibold">
                    {can.update && <Link href={`/library/books/${book.id}/edit`} className="text-teal-800 hover:text-teal-950">Edit book</Link>}
                    {can.delete && <button type="button" onClick={deleteBook} className="text-rose-700 hover:text-rose-900">Delete</button>}
                </div>
            </div>

            <section className="mb-6 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h2 className="font-semibold text-slate-900">Details</h2>
                    <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${book.isActive ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-600'}`}>{book.isActive ? 'Active' : 'Inactive'}</span>
                </div>
                <dl className="mt-4 grid gap-4 text-sm sm:grid-cols-2 xl:grid-cols-4">
                    <Detail label="Author" value={book.author} />
                    <Detail label="Category" value={book.category} />
                    <Detail label="ISBN" value={book.isbn} />
                    <Detail label="Publisher" value={book.publisher} />
                    <div className="sm:col-span-2 xl:col-span-4"><Detail label="Description" value={book.description} /></div>
                </dl>
                {!can.delete && can.update && <p className="mt-4 text-xs text-slate-500">This title has loan history, so it can be deactivated but not deleted.</p>}
            </section>

            <section className="mb-6 grid gap-3 sm:grid-cols-3">
                {[['Copies owned', stock.copies], ['On the shelf', stock.available], ['On loan', stock.issued]].map(([label, value]) => (
                    <article key={label} className="rounded-2xl border border-slate-200 bg-white p-5">
                        <p className="text-sm text-slate-500">{label}</p>
                        <p className="mt-2 text-2xl font-semibold tracking-tight text-slate-900">{value}</p>
                    </article>
                ))}
            </section>

            <section className="mb-6 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <h2 className="font-semibold text-slate-900">Copies</h2>
                <p className="mt-1 text-sm text-slate-500">Each physical copy has its own accession number. Issue a copy from here; returns and renewals are in the loan register.</p>
                {copies.length === 0 ? (
                    <p className="mt-4 rounded-xl bg-slate-50 px-3 py-4 text-sm text-slate-500">No copies registered.</p>
                ) : (
                    <ul className="mt-4 divide-y divide-slate-100 rounded-xl border border-slate-100">
                        {copies.map((copy) => <CopyRow key={copy.id} copy={copy} can={can} />)}
                    </ul>
                )}
                {can.addCopies && <AddCopies bookId={book.id} />}
            </section>

            <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <h2 className="font-semibold text-slate-900">Recent loans</h2>
                {history.length === 0 ? (
                    <p className="mt-4 rounded-xl bg-slate-50 px-3 py-4 text-sm text-slate-500">This title has never been loaned.</p>
                ) : (
                    <div className="mt-4 overflow-x-auto">
                        <table className="w-full min-w-[620px] text-left text-sm">
                            <thead className="border-b border-slate-100 text-xs text-slate-500"><tr><th className="py-3 pr-4">Copy</th><th className="py-3 pr-4">Student</th><th className="py-3 pr-4">Issued</th><th className="py-3 pr-4">Due</th><th className="py-3">Status</th></tr></thead>
                            <tbody className="divide-y divide-slate-100">
                                {history.map((loan) => (
                                    <tr key={loan.id}>
                                        <td className="py-3 pr-4">{loan.accessionNumber}</td>
                                        <td className="py-3 pr-4">{loan.studentName}<span className="block text-xs text-slate-500">{loan.admissionNumber}</span></td>
                                        <td className="py-3 pr-4">{dateLabel(loan.issueDate)}</td>
                                        <td className="py-3 pr-4">{dateLabel(loan.dueDate)}</td>
                                        <td className="py-3"><span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${loanTone(loan.status)}`}>{LOAN_LABELS[loan.status]}</span>{loan.returnedAt && <span className="ml-2 text-xs text-slate-500">returned {dateLabel(loan.returnedAt)}</span>}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>
        </AppLayout>
    );
}

function AddCopies({ bookId }) {
    const form = useForm({ count: 1, shelf_location: '' });

    function submit(event) {
        event.preventDefault();
        form.post(`/library/books/${bookId}/copies`, { preserveScroll: true });
    }

    return (
        <form onSubmit={submit} className="mt-4 grid gap-2 rounded-xl bg-slate-50 p-4 sm:grid-cols-[10rem_1fr_auto] sm:items-end">
            <Field label="Additional copies" error={form.errors.count}>
                <input type="number" min="1" max="100" value={form.data.count} onChange={(event) => form.setData('count', event.target.value)} className={inputClass(form.errors.count)} required />
            </Field>
            <Field label="Shelf location" error={form.errors.shelf_location}>
                <input value={form.data.shelf_location} onChange={(event) => form.setData('shelf_location', event.target.value)} className={inputClass(form.errors.shelf_location)} maxLength={100} />
            </Field>
            <button type="submit" disabled={form.processing} className="rounded-lg border border-teal-200 px-4 py-2.5 text-sm font-semibold text-teal-800 hover:bg-teal-50 disabled:opacity-50">{form.processing ? 'Adding…' : 'Add copies'}</button>
            {form.errors.book && <p role="alert" className="text-sm text-rose-700 sm:col-span-3">{form.errors.book}</p>}
        </form>
    );
}

function Detail({ label, value }) {
    return (
        <div>
            <dt className="text-xs font-medium text-slate-500">{label}</dt>
            <dd className="mt-1 text-slate-900">{value || '—'}</dd>
        </div>
    );
}