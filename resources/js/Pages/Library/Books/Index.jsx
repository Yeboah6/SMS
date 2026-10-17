import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../../Layouts/AppLayout';
import PageAlerts from '../../../Components/Exams/PageAlerts';
import LibraryNav from '../../../Components/Books/LibraryNav';

export default function BooksIndex({ books, filters, categories, totals, can }) {
    const [search, setSearch] = useState(filters.search || '');

    function visit(overrides = {}) {
        const params = { search, category: filters.category, stock: filters.stock, state: filters.state, ...overrides };
        if (params.state === 'active') delete params.state;
        Object.keys(params).forEach((key) => !params[key] && delete params[key]);
        router.get('/library/books', params, { preserveState: true, replace: true });
    }

    function deleteBook(book) {
        if (window.confirm(`Delete ${book.title} and all its copies? Titles with loan history cannot be deleted.`)) {
            router.delete(`/library/books/${book.id}`, { preserveScroll: true });
        }
    }

    const cards = [['Active titles', totals.titles], ['Copies owned', totals.copies], ['On the shelf', totals.available], ['On loan', totals.issued], ['Overdue', totals.overdue]];

    return (
        <AppLayout title="Books inventory" description="Every title the library owns and the stock of each.">
            <Head title="Books inventory" />
            <PageAlerts />
            <LibraryNav current="inventory" />

            <section className="mb-6 grid gap-3 sm:grid-cols-3 xl:grid-cols-5">
                {cards.map(([label, value]) => (
                    <article key={label} className="rounded-2xl border border-slate-200 bg-white p-5">
                        <p className="text-sm text-slate-500">{label}</p>
                        <p className={`mt-2 text-2xl font-semibold tracking-tight ${label === 'Overdue' && value > 0 ? 'text-rose-700' : 'text-slate-900'}`}>{value}</p>
                    </article>
                ))}
            </section>

            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div className="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 xl:flex-row xl:items-center xl:justify-between">
                    <h2 className="font-semibold text-slate-900">{books.total.toLocaleString()} title{books.total === 1 ? '' : 's'}</h2>
                    <div className="flex flex-wrap items-center gap-3">
                        <form onSubmit={(event) => { event.preventDefault(); visit(); }} className="flex gap-2">
                            <input type="search" aria-label="Search books" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Title, author or ISBN" className="w-48 rounded-xl border border-slate-200 px-3 py-2 text-sm" />
                            <button className="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Search</button>
                        </form>
                        <select aria-label="Category" value={filters.category} onChange={(event) => visit({ category: event.target.value })} className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                            <option value="">All categories</option>
                            {categories.map((category) => <option key={category} value={category}>{category}</option>)}
                        </select>
                        <select aria-label="Stock" value={filters.stock} onChange={(event) => visit({ stock: event.target.value })} className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                            <option value="">Any stock</option>
                            <option value="available">Has copies on the shelf</option>
                            <option value="unavailable">None on the shelf</option>
                        </select>
                        <select aria-label="Catalogue status" value={filters.state} onChange={(event) => visit({ state: event.target.value })} className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="all">All</option>
                        </select>
                        {can.create && <Link href="/library/books/create" className="rounded-xl bg-teal-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-900">Add book</Link>}
                    </div>
                </div>

                {books.data.length === 0 ? (
                    <p className="px-6 py-14 text-center text-sm text-slate-500">No books match. {can.create && 'Add the first title to start the inventory.'}</p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[820px] text-left text-sm">
                            <thead className="bg-slate-50 text-xs text-slate-500">
                                <tr><th className="px-5 py-3">Title</th><th className="px-5 py-3">Category</th><th className="px-5 py-3 text-right">Copies</th><th className="px-5 py-3 text-right">On shelf</th><th className="px-5 py-3 text-right">On loan</th><th className="px-5 py-3"><span className="sr-only">Actions</span></th></tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {books.data.map((book) => (
                                    <tr key={book.id} className="hover:bg-slate-50/70">
                                        <td className="px-5 py-3.5">
                                            <Link href={`/library/books/${book.id}`} className="font-medium text-teal-800 hover:text-teal-950">{book.title}</Link>
                                            {!book.isActive && <span className="ml-2 rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600">Inactive</span>}
                                            <p className="mt-0.5 text-xs text-slate-500">{book.author}{book.isbn ? ` · ISBN ${book.isbn}` : ''}</p>
                                        </td>
                                        <td className="px-5 py-3.5 text-slate-600">{book.category}</td>
                                        <td className="px-5 py-3.5 text-right text-slate-700">{book.copies}</td>
                                        <td className={`px-5 py-3.5 text-right font-medium ${book.available === 0 ? 'text-rose-700' : 'text-slate-900'}`}>{book.available}</td>
                                        <td className="px-5 py-3.5 text-right text-slate-700">{book.issued}</td>
                                        <td className="px-5 py-3.5">
                                            <div className="flex items-center justify-end gap-3 text-xs font-semibold">
                                                <Link href={`/library/books/${book.id}`} className="text-teal-800 hover:text-teal-950">View</Link>
                                                {can.update && <Link href={`/library/books/${book.id}/edit`} className="text-teal-800 hover:text-teal-950">Edit</Link>}
                                                {can.delete && <button type="button" onClick={() => deleteBook(book)} className="text-rose-700 hover:text-rose-900">Delete</button>}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {books.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-slate-100 px-5 py-3">
                        <button type="button" disabled={books.current_page <= 1} onClick={() => visit({ page: books.current_page - 1 })} className="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 disabled:opacity-40">Previous</button>
                        <span className="text-xs text-slate-500">Page {books.current_page} of {books.last_page}</span>
                        <button type="button" disabled={books.current_page >= books.last_page} onClick={() => visit({ page: books.current_page + 1 })} className="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 disabled:opacity-40">Next</button>
                    </div>
                )}
            </section>
        </AppLayout>
    );
}