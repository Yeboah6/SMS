import { Link, useForm } from '@inertiajs/react';
import { Field, inputClass } from '../Auth/Login';

export default function StudentNoteForm({ student, note = null, today }) {
    const form = useForm({
        note_type: note?.type || 'academic',
        title: note?.title || '',
        content: note?.content || '',
        note_date: note?.date || today,
        visibility: note?.visibility || 'staff_only',
        attachment: null,
        remove_attachment: false,
    });
    const baseUrl = `/students/${student.id}/notes`;

    function submit(event) {
        event.preventDefault();
        const options = {
            forceFormData: true,
            preserveScroll: true,
        };

        if (note) {
            form.put(`${baseUrl}/${note.id}`, options);
        } else {
            form.post(baseUrl, options);
        }
    }

    return (
        <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
            <div className="mb-5">
                <Link href={baseUrl} className="text-sm font-semibold text-teal-800 hover:text-teal-950">← Notes for {student.name}</Link>
                <h2 className="mt-3 text-xl font-semibold text-slate-900">{note ? 'Edit note' : 'Create note'}</h2>
            </div>
            <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                <Field label="Note type" error={form.errors.note_type}>
                    <select value={form.data.note_type} onChange={(event) => form.setData('note_type', event.target.value)} className={inputClass(form.errors.note_type)}>
                        <option value="academic">Academic</option>
                        <option value="behaviour">Behaviour</option>
                        <option value="welfare">Welfare</option>
                        <option value="parent_communication">Parent communication</option>
                        <option value="medical">Medical</option>
                        <option value="general">General</option>
                    </select>
                </Field>
                <Field label="Note date" error={form.errors.note_date}>
                    <input type="date" max={today} value={form.data.note_date} onChange={(event) => form.setData('note_date', event.target.value)} className={inputClass(form.errors.note_date)} required />
                </Field>
                <Field label="Title" error={form.errors.title}>
                    <input value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} className={inputClass(form.errors.title)} maxLength={255} required />
                </Field>
                <Field label="Visibility" error={form.errors.visibility}>
                    <select value={form.data.visibility} onChange={(event) => form.setData('visibility', event.target.value)} className={inputClass(form.errors.visibility)}>
                        <option value="staff_only">School staff only</option>
                        <option value="guardians">Guardians</option>
                        <option value="student">Student</option>
                    </select>
                </Field>
                <div className="sm:col-span-2">
                    <Field label="Note" error={form.errors.content}>
                        <textarea rows={5} value={form.data.content} onChange={(event) => form.setData('content', event.target.value)} className={inputClass(form.errors.content)} maxLength={10000} required />
                    </Field>
                </div>
                {note?.attachmentName && (
                    <div className="sm:col-span-2 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-700">
                        Current file: {note.attachmentName}
                        <label className="mt-2 flex items-center gap-2 text-xs text-rose-700">
                            <input type="checkbox" checked={form.data.remove_attachment} onChange={(event) => form.setData('remove_attachment', event.target.checked)} />
                            Remove current attachment
                        </label>
                        {form.errors.remove_attachment && <p role="alert" className="mt-1 text-xs text-rose-700">{form.errors.remove_attachment}</p>}
                    </div>
                )}
                <div className="sm:col-span-2">
                    <Field label="Attachment (PDF or image; up to 10 MB)" error={form.errors.attachment}>
                        <input
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png,.webp"
                            onChange={(event) => {
                                form.setData('attachment', event.target.files?.[0] || null);
                                if (event.target.files?.length) {
                                    form.setData('remove_attachment', false);
                                }
                            }}
                            className={inputClass(form.errors.attachment)}
                        />
                    </Field>
                </div>
                <div className="flex flex-wrap gap-2 sm:col-span-2">
                    <button type="submit" disabled={form.processing} className="rounded-xl bg-teal-800 px-4 py-3 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-60">
                        {form.processing ? 'Saving…' : note ? 'Save changes' : 'Create note'}
                    </button>
                    <Link href={note ? `${baseUrl}/${note.id}` : baseUrl} className="rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</Link>
                </div>
            </form>
        </section>
    );
}
