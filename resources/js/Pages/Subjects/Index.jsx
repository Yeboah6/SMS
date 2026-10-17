import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import StatusBanner from '../../Components/StatusBanner';

export default function SubjectsIndex({ subjects, filters, can }) {
    const [query, setQuery] = useState(filters.q || '');

    function visit(params) {
        router.get('/subjects', params, { preserveState: true, replace: true });
    }

    function search(event) {
        event.preventDefault();
        visit(query.trim() ? { q: query.trim() } : {});
    }

    function goToPage(page) {
        visit({ ...(filters.q ? { q: filters.q } : {}), page });
    }

    function deleteSubject(subject) {
        if (window.confirm(`Delete ${subject.name}? Subjects assigned to classes or teachers cannot be deleted.`)) {
            router.delete(`/subjects/${subject.id}`, { preserveScroll: true });
        }
    }

    return (
        <AppLayout title="Subjects" description="Maintain the school's subject catalog for class and teacher assignments.">
            <Head title="Subjects" />
            <StatusBanner />

            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div className="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 className="font-semibold text-slate-900">Subject catalog</h2>
                        <p className="mt-1 text-sm text-slate-500">{subjects.total.toLocaleString()} subjects</p>
                    </div>
                    <div className="flex flex-wrap items-center gap-3">
                        <form onSubmit={search} className="flex gap-2">
                            <input type="search" value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Search code or name" aria-label="Search subjects" className="w-48 rounded-xl border border-slate-200 px-3 py-2 text-sm" />
                            <button className="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Search</button>
                        </form>
                        {can.create && <Link href="/subjects/create" className="rounded-xl bg-teal-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-900">Add subject</Link>}
                    </div>
                </div>

                {subjects.data.length === 0 ? (
                    <p className="px-6 py-12 text-center text-sm text-slate-500">
                        {filters.q ? `No subjects match “${filters.q}”.` : 'No subjects have been created.'}
                    </p>
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {subjects.data.map((subject) => (
                            <li key={subject.id} className="flex flex-wrap items-center gap-4 px-5 py-4">
                                <span className="w-20 shrink-0 rounded-lg bg-slate-100 px-2 py-1 text-center font-mono text-xs font-semibold text-slate-600">{subject.code}</span>
                                <div className="min-w-0 flex-1">
                                    <Link href={`/subjects/${subject.id}`} className="block truncate text-sm font-medium text-slate-900 hover:text-teal-800">{subject.name}</Link>
                                    {subject.description && <p className="mt-0.5 truncate text-xs text-slate-500">{subject.description}</p>}
                                </div>
                                <div className="flex items-center gap-3 text-xs font-semibold">
                                    <Link href={`/subjects/${subject.id}`} className="text-teal-800 hover:text-teal-950">View</Link>
                                    {can.update && <Link href={`/subjects/${subject.id}/edit`} className="text-teal-800 hover:text-teal-950">Edit</Link>}
                                    {can.delete && <button type="button" onClick={() => deleteSubject(subject)} className="text-rose-700 hover:text-rose-900">Delete</button>}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}

                {subjects.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-slate-100 px-5 py-3">
                        <button type="button" disabled={subjects.current_page <= 1} onClick={() => goToPage(subjects.current_page - 1)} className="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 disabled:opacity-40">Previous</button>
                        <span className="text-xs text-slate-500">Page {subjects.current_page} of {subjects.last_page}</span>
                        <button type="button" disabled={subjects.current_page >= subjects.last_page} onClick={() => goToPage(subjects.current_page + 1)} className="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 disabled:opacity-40">Next</button>
                    </div>
                )}
            </section>
        </AppLayout>
    );
}