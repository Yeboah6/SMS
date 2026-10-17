import { Link } from '@inertiajs/react';
import { Field, inputClass } from '../../Pages/Auth/Login';

/** Shared by Classes/Create (pass `years`) and Classes/Edit (omit `years`). */
export default function ClassForm({ form, onSubmit, years = null, submitLabel, cancelHref }) {
    return (
        <form onSubmit={onSubmit} className="grid gap-4 sm:grid-cols-2">
            {years && (
                <div className="sm:col-span-2">
                    <Field label="Academic year" error={form.errors.academic_year_id}>
                        <select value={form.data.academic_year_id} onChange={(event) => form.setData('academic_year_id', event.target.value)} className={inputClass(form.errors.academic_year_id)} required>
                            <option value="">Select an academic year</option>
                            {years.map((year) => <option key={year.id} value={year.id}>{year.name} · {year.status}</option>)}
                        </select>
                    </Field>
                </div>
            )}
            <Field label="Class name" error={form.errors.name}>
                <input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} className={inputClass(form.errors.name)} placeholder="Class 1" required />
            </Field>
            <Field label="Display order" error={form.errors.sort_order}>
                <input type="number" min="0" max="9999" value={form.data.sort_order} onChange={(event) => form.setData('sort_order', event.target.value)} className={inputClass(form.errors.sort_order)} required />
            </Field>
            <div className="flex gap-3 sm:col-span-2">
                <button disabled={form.processing} className="rounded-xl bg-teal-800 px-5 py-3 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-60">{submitLabel}</button>
                <Link href={cancelHref} className="rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</Link>
            </div>
        </form>
    );
}