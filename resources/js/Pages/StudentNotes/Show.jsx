import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

export default function StudentNotesShow({ student, note, canEdit, canDelete }) {
    const { status } = usePage().props;
    const deleteForm = useForm({});

    function deleteNote() {
        if (window.confirm(`Delete the note "${note.title}"? This action cannot be undone.`)) {
            deleteForm.delete(`/students/${student.id}/notes/${note.id}`, { preserveScroll: true });
        }
    }

    return (
        <AppLayout title={note.title} description={`${student.name} · Student note`}>
            <Head title={note.title} />
            {status && <div role="status" className="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{status}</div>}
            <article className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <Link href={`/students/${student.id}/notes`} className="text-sm font-semibold text-teal-800 hover:text-teal-950">← Notes for {student.name}</Link>
                        <h2 className="mt-3 text-2xl font-bold tracking-tight text-slate-950">{note.title}</h2>
                        <p className="mt-2 text-sm capitalize text-slate-500">{note.type.replaceAll('_', ' ')} · {note.date} · by {note.author}</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {canEdit && <Link href={`/students/${student.id}/notes/${note.id}/edit`} className="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Edit</Link>}
                        {canDelete && <button type="button" onClick={deleteNote} disabled={deleteForm.processing} className="rounded-xl border border-rose-200 px-4 py-2.5 text-sm font-semibold text-rose-700 hover:bg-rose-50 disabled:opacity-60">Delete</button>}
                    </div>
                </div>
                <div className="mt-5 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4">
                    <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium capitalize text-slate-600">{note.visibility.replaceAll('_', ' ')}</span>
                    {note.attachmentUrl && <a href={note.attachmentUrl} className="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Download {note.attachmentName} · {(note.attachmentSize / 1024).toFixed(0)} KB</a>}
                </div>
                <p className="mt-5 whitespace-pre-wrap text-sm leading-7 text-slate-700">{note.content}</p>
            </article>
        </AppLayout>
    );
}
