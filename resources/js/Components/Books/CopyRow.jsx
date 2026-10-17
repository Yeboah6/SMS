import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Field, inputClass } from '../../Pages/Auth/Login';
import StudentPicker from './StudentPicker';
import { copyTone, dateLabel } from '../../lib/library';

/** One physical copy: its status, shelf, and the actions that apply to it. */
export default function CopyRow({ copy, can }) {
    const [mode, setMode] = useState(null);

    function remove() {
        if (window.confirm(`Delete copy ${copy.accessionNumber}? This cannot be undone.`)) {
            router.delete(`/library/copies/${copy.id}`, { preserveScroll: true });
        }
    }

    function returnBook() {
        router.post(`/library/loans/${copy.loan.id}/return`, {}, { preserveScroll: true });
    }

    return (
        <li className="px-4 py-3">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p className="text-sm font-medium text-slate-900">{copy.accessionNumber}</p>
                    <p className="mt-0.5 text-xs text-slate-500">{copy.shelfLocation || 'No shelf location'}</p>
                </div>
                <div className="flex flex-wrap items-center gap-3">
                    <span className={`rounded-full px-2.5 py-1 text-xs font-semibold capitalize ${copyTone(copy.status)}`}>{copy.status === 'issued' ? 'On loan' : copy.status}</span>
                    {copy.status === 'available' && can.issue && <button type="button" onClick={() => setMode(mode === 'issue' ? null : 'issue')} className="text-xs font-semibold text-teal-800 hover:text-teal-950">Issue</button>}
                    {can.update && <button type="button" onClick={() => setMode(mode === 'edit' ? null : 'edit')} className="text-xs font-semibold text-teal-800 hover:text-teal-950">Edit shelf</button>}
                    {copy.canRemove && can.delete && <button type="button" onClick={remove} className="text-xs font-semibold text-rose-700 hover:text-rose-900">Delete</button>}
                </div>
            </div>

            {copy.loan && (
                <div className="mt-2 flex flex-wrap items-center justify-between gap-2 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">
                    <span>{copy.loan.studentName} · {copy.loan.admissionNumber} · due {dateLabel(copy.loan.dueDate)}{copy.loan.overdue && <span className="ml-1 font-semibold text-rose-700">Overdue</span>}</span>
                    {can.return && <button type="button" onClick={returnBook} className="font-semibold text-teal-800 hover:text-teal-950">Return book</button>}
                </div>
            )}

            {mode === 'issue' && <IssueForm copy={copy} onDone={() => setMode(null)} />}
            {mode === 'edit' && <ShelfForm copy={copy} onDone={() => setMode(null)} />}
        </li>
    );
}

function IssueForm({ copy, onDone }) {
    const form = useForm({ student_id: '', due_date: '' });

    function submit(event) {
        event.preventDefault();
        form.post(`/library/copies/${copy.id}/issue`, { preserveScroll: true, onSuccess: onDone });
    }

    return (
        <form onSubmit={submit} className="mt-3 grid gap-2 sm:grid-cols-[1fr_12rem_auto] sm:items-end">
            <StudentPicker onChange={(id) => form.setData('student_id', id)} error={form.errors.student_id} />
            <Field label="Due date" error={form.errors.due_date}>
                <input type="date" value={form.data.due_date} onChange={(event) => form.setData('due_date', event.target.value)} className={inputClass(form.errors.due_date)} required />
            </Field>
            <button type="submit" disabled={form.processing || !form.data.student_id} className="rounded-lg bg-teal-700 px-3 py-2.5 text-sm font-semibold text-white hover:bg-teal-800 disabled:opacity-50">{form.processing ? 'Issuing…' : 'Issue copy'}</button>
            {form.errors.book_copy && <p role="alert" className="text-sm text-rose-700 sm:col-span-3">{form.errors.book_copy}</p>}
        </form>
    );
}

function ShelfForm({ copy, onDone }) {
    const form = useForm({ shelf_location: copy.shelfLocation || '' });

    function submit(event) {
        event.preventDefault();
        form.patch(`/library/copies/${copy.id}`, { preserveScroll: true, onSuccess: onDone });
    }

    return (
        <form onSubmit={submit} className="mt-3 flex flex-wrap items-end gap-2">
            <Field label="Shelf location" error={form.errors.shelf_location}>
                <input value={form.data.shelf_location} onChange={(event) => form.setData('shelf_location', event.target.value)} className={inputClass(form.errors.shelf_location)} maxLength={100} />
            </Field>
            <button type="submit" disabled={form.processing} className="rounded-lg border border-teal-200 px-3 py-2.5 text-sm font-semibold text-teal-800 hover:bg-teal-50 disabled:opacity-50">Save</button>
        </form>
    );
}