import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { Field, inputClass } from '../Auth/Login';
import { LOAN_LABELS, dateLabel, loanTone } from '../../lib/library';

/** Students and parents: browse the catalogue and see what is borrowed. */
export default function LibraryPortal({ mode, books, loans, students, selectedStudentId }) {
    return (
        <AppLayout title="Library" description="Browse the catalogue and check your borrowed books.">
            <Head title="Library" />

            {mode === 'parent' && (
                <section className="mb-6 flex flex-wrap items-end justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-5">
                    <div>
                        <h2 className="font-semibold text-slate-900">Linked student loans</h2>
                        <p className="mt-1 text-sm text-slate-500">Only library activity for linked students is shown.</p>
                    </div>
                    <Field label="Student">
                        <select value={selectedStudentId || ''} onChange={(event) => router.get('/library', event.target.value ? { student_id: event.target.value } : {}, { preserveScroll: true })} className={inputClass(false)}>
                            <option value="">All linked students</option>
                            {students.map((student) => <option key={student.id} value={student.id}>{student.name}</option>)}
                        </select>
                    </Field>
                </section>
            )}

            <section className="mb-6">
                <h2 className="font-semibold text-slate-900">My borrowed books</h2>
                <p className="mt-1 mb-3 text-sm text-slate-500">{loans.total} loan record{loans.total === 1 ? '' : 's'}</p>
                {loans.data.length === 0 ? (
                    <p className="rounded-2xl border border-slate-200 bg-white px-6 py-10 text-center text-sm text-slate-500">No loans found.</p>
                ) : (
                    <div className="grid gap-3">
                        {loans.data.map((loan) => (
                            <article key={loan.id} className="rounded-2xl border border-slate-200 bg-white p-5">
                                <div className="flex flex-wrap items-center gap-2">
                                    <h3 className="font-semibold text-slate-900">{loan.bookTitle}</h3>
                                    <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${loanTone(loan.status)}`}>{LOAN_LABELS[loan.status]}</span>
                                </div>
                                <p className="mt-1 text-sm text-slate-600">{loan.author} · {loan.accessionNumber}</p>
                                {mode === 'parent' && <p className="mt-1 text-sm text-slate-600">{loan.studentName}</p>}
                                <p className="mt-1 text-xs text-slate-500">Issued {dateLabel(loan.issueDate)} · Due {dateLabel(loan.dueDate)}{loan.returnedAt ? ` · Returned ${dateLabel(loan.returnedAt)}` : ''}{loan.renewalCount ? ` · Renewed ${loan.renewalCount} time${loan.renewalCount === 1 ? '' : 's'}` : ''}</p>
                            </article>
                        ))}
                    </div>
                )}
                {loans.links.length > 3 && (
                    <nav aria-label="Loan pages" className="mt-4 flex flex-wrap gap-2">
                        {loans.links.map((link, index) => (
                            <Link key={`${link.label}-${index}`} href={link.url || '#'} preserveScroll className={`rounded-lg px-3 py-2 text-sm ${link.active ? 'bg-teal-700 text-white' : 'border border-slate-200 bg-white text-slate-700'} ${!link.url ? 'pointer-events-none opacity-50' : ''}`}>
                                {link.label.replace('&laquo;', '‹').replace('&raquo;', '›')}
                            </Link>
                        ))}
                    </nav>
                )}
            </section>

            <section>
                <h2 className="font-semibold text-slate-900">Catalogue</h2>
                <p className="mt-1 mb-3 text-sm text-slate-500">{books.length} title{books.length === 1 ? '' : 's'}</p>
                {books.length === 0 ? (
                    <p className="rounded-2xl border border-slate-200 bg-white px-6 py-10 text-center text-sm text-slate-500">No books are currently in the catalogue.</p>
                ) : (
                    <div className="grid gap-3 xl:grid-cols-2">
                        {books.map((book) => (
                            <article key={book.id} className="rounded-2xl border border-slate-200 bg-white p-5">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <h3 className="font-semibold text-slate-900">{book.title}</h3>
                                        <p className="mt-1 text-sm text-slate-600">{book.author} · {book.category}</p>
                                        {book.isbn && <p className="mt-1 text-xs text-slate-500">ISBN {book.isbn}</p>}
                                    </div>
                                    <span className={`rounded-full px-3 py-1 text-xs font-semibold ${book.availableCopies > 0 ? 'bg-teal-50 text-teal-800' : 'bg-slate-100 text-slate-600'}`}>{book.availableCopies > 0 ? `${book.availableCopies} available` : 'All on loan'}</span>
                                </div>
                            </article>
                        ))}
                    </div>
                )}
            </section>
        </AppLayout>
    );
}