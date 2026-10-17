import { Link } from '@inertiajs/react';
import { Field, inputClass } from '../../Pages/Auth/Login';

/** Shared by Subjects/Create and Subjects/Edit. */
export default function SubjectForm({ form, onSubmit, submitLabel, cancelHref }) {
    return (
        <form onSubmit={onSubmit} className="grid gap-4 sm:grid-cols-2">
            <Field label="Subject code" error={form.errors.code}>
                <input value={form.data.code} onChange={(event) => form.setData('code', event.target.value)} className={inputClass(form.errors.code)} placeholder="MATH" required />
            </Field>
            <Field label="Subject name" error={form.errors.name}>
                <input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} className={inputClass(form.errors.name)} placeholder="Mathematics" required />
            </Field>
            <div className="sm:col-span-2">
                <Field label="Description" error={form.errors.description}>
                    <textarea rows="3" value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} className={inputClass(form.errors.description)} />
                </Field>
            </div>
            <div className="flex gap-3 sm:col-span-2">
                <button disabled={form.processing} className="rounded-xl bg-teal-800 px-5 py-3 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-60">{submitLabel}</button>
                <Link href={cancelHref} className="rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</Link>
            </div>
        </form>
    );
}