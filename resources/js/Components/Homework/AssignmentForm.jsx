import { Link } from '@inertiajs/react';
import { Field, inputClass } from '../../Pages/Auth/Login';

/**
 * Shared by Homework/Create (choose the section and subject you teach) and Homework/Edit
 * (the section and subject are fixed once an assignment exists).
 */
export default function AssignmentForm({ form, onSubmit, subjectAssignments = null, fixedScope = null, submitLabel, cancelHref }) {
    function chooseScope(value) {
        const option = subjectAssignments.find((item) => `${item.sectionId}:${item.subjectId}` === value);
        if (option) form.setData({ ...form.data, section_id: option.sectionId, subject_id: option.subjectId });
    }

    return (
        <form onSubmit={onSubmit} className="grid gap-4 sm:grid-cols-2">
            <div className="sm:col-span-2">
                {fixedScope ? (
                    <div><p className="text-sm font-medium text-slate-700">Section and subject</p><p className="mt-1 text-sm text-slate-900">{fixedScope}</p></div>
                ) : (
                    <Field label="Section and subject" error={form.errors.section_id || form.errors.subject_id}>
                        <select value={`${form.data.section_id}:${form.data.subject_id}`} onChange={(event) => chooseScope(event.target.value)} className={inputClass(form.errors.section_id || form.errors.subject_id)} required>
                            {subjectAssignments.map((item) => <option key={`${item.sectionId}:${item.subjectId}`} value={`${item.sectionId}:${item.subjectId}`}>{item.label}</option>)}
                        </select>
                    </Field>
                )}
            </div>
            <Field label="Title" error={form.errors.title}>
                <input value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} className={inputClass(form.errors.title)} maxLength={255} required />
            </Field>
            <Field label="Maximum points" error={form.errors.max_points}>
                <input type="number" min="1" max="10000" step="0.01" value={form.data.max_points} onChange={(event) => form.setData('max_points', event.target.value)} className={inputClass(form.errors.max_points)} required />
            </Field>
            <Field label="Assigned date" error={form.errors.assigned_date}>
                <input type="date" value={form.data.assigned_date} onChange={(event) => form.setData('assigned_date', event.target.value)} className={inputClass(form.errors.assigned_date)} required />
            </Field>
            <Field label="Due date" error={form.errors.due_date}>
                <input type="date" value={form.data.due_date} onChange={(event) => form.setData('due_date', event.target.value)} className={inputClass(form.errors.due_date)} required />
            </Field>
            <div className="sm:col-span-2">
                <Field label="Instructions" error={form.errors.instructions}>
                    <textarea value={form.data.instructions} onChange={(event) => form.setData('instructions', event.target.value)} className={inputClass(form.errors.instructions)} rows={6} maxLength={10000} required />
                </Field>
            </div>
            <div className="flex flex-wrap gap-3 sm:col-span-2">
                <button type="submit" disabled={form.processing} className="rounded-xl bg-teal-800 px-5 py-3 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-60">{form.processing ? 'Saving…' : submitLabel}</button>
                <Link href={cancelHref} className="rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</Link>
            </div>
        </form>
    );
}