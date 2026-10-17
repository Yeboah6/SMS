import { Link } from '@inertiajs/react';
import { Field, inputClass } from '../Pages/Auth/Login';

/** Shared by Sections/Create and Sections/Edit. */
export default function SectionForm({ form, onSubmit, submitLabel, cancelHref }) {
    return (
        <form onSubmit={onSubmit} className="grid gap-4 sm:grid-cols-2">
            <Field label="Section name" error={form.errors.name}>
                <input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} className={inputClass(form.errors.name)} placeholder="A" required />
            </Field>
            <Field label="Capacity" error={form.errors.capacity}>
                <input type="number" min="1" max="5000" value={form.data.capacity} onChange={(event) => form.setData('capacity', event.target.value)} className={inputClass(form.errors.capacity)} placeholder="Optional" />
            </Field>
            <div className="flex gap-3 sm:col-span-2">
                <button disabled={form.processing} className="rounded-xl bg-teal-800 px-5 py-3 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-60">{submitLabel}</button>
                <Link href={cancelHref} className="rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</Link>
            </div>
        </form>
    );
}