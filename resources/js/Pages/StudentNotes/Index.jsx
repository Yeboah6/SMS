import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

export default function StudentNotesIndex({ student, notes, canCreate }) {
    const { status } = usePage().props;

    return (
        <AppLayout title="Student notes" description={`${student.name} · ${student.admissionNumber}`}>
            <Head title={`Notes · ${student.name}`} />
            {status && <div role="status" className="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{status}</div>}

            <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <Link href={`/students/${student.id}`} className="text-sm font-semibold text-teal-800 hover:text-teal-950">← {student.name}</Link>
                        <h2 className="mt-2 text-xl font-semibold text-slate-900">Notes</h2>
                        <p className="mt-1 text-sm text-slate-500">Private student notes and their change history.</p>
                    </div>
                    {canCreate && <Link href={`/students/${student.id}/notes/create`} className="rounded-xl bg-teal-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-900">Add note</Link>}
                </div>

                {notes.data.length === 0 ? (
                    <p className="mt-6 rounded-xl bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">No notes have been recorded.</p>
                ) : (
                    <div className="mt-5 divide-y divide-slate-100">
                        {notes.data.map((note) => (
                            <article key={note.id} className="flex flex-wrap items-start justify-between gap-3 py-4">
                                <div className="min-w-0">
                                    <Link href={`/students/${student.id}/notes/${note.id}`} className="font-semibold text-slate-900 hover:text-teal-800">{note.title}</Link>
                                    <p className="mt-1 text-xs capitalize text-slate-500">{note.type.replaceAll('_', ' ')} · {note.date} · {note.author}</p>
                                    <span className="mt-2 inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-medium capitalize text-slate-600">{note.visibility.replaceAll('_', ' ')}</span>
                                </div>
                                <div className="flex gap-2">
                                    {note.canEdit && <Link href={`/students/${student.id}/notes/${note.id}/edit`} className="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Edit</Link>}
                                    {note.canDelete && <Link href={`/students/${student.id}/notes/${note.id}`} className="rounded-lg border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50">View / delete</Link>}
                                </div>
                            </article>
                        ))}
                    </div>
                )}

                {notes.links.length > 3 && (
                    <nav aria-label="Notes pagination" className="mt-5 flex flex-wrap gap-2">
                        {notes.links.map((link, index) => link.url ? (
                            <Link key={index} href={link.url} preserveScroll className={`rounded-lg border px-3 py-2 text-xs font-medium ${link.active ? 'border-teal-800 bg-teal-800 text-white' : 'border-slate-200 text-slate-600 hover:bg-slate-50'}`} dangerouslySetInnerHTML={{ __html: link.label }} />
                        ) : <span key={index} className="rounded-lg border border-slate-100 px-3 py-2 text-xs text-slate-400" dangerouslySetInnerHTML={{ __html: link.label }} />)}
                    </nav>
                )}
            </section>
        </AppLayout>
    );
}
