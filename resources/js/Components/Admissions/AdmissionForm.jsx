import { Link } from '@inertiajs/react';
import { Field, inputClass } from '../../Pages/Auth/Login';
import { GENDER_OPTIONS, SOURCE_OPTIONS } from '../../lib/admissions';

/** Shared by Admissions/Create and Admissions/Edit. */
export default function AdmissionForm({ form, onSubmit, today, submitLabel, cancelHref }) {
    const set = (key) => (event) => form.setData(key, event.target.value);

    return (
        <form onSubmit={onSubmit} className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <Field label="Parent / guardian name" error={form.errors.parent_name}>
                <input value={form.data.parent_name} onChange={set('parent_name')} className={inputClass(form.errors.parent_name)} required />
            </Field>
            <Field label="Parent phone" error={form.errors.parent_phone}>
                <input type="tel" value={form.data.parent_phone} onChange={set('parent_phone')} className={inputClass(form.errors.parent_phone)} required />
            </Field>
            <Field label="Parent email" error={form.errors.parent_email}>
                <input type="email" value={form.data.parent_email} onChange={set('parent_email')} className={inputClass(form.errors.parent_email)} />
            </Field>
            <Field label="Child first name" error={form.errors.child_first_name}>
                <input value={form.data.child_first_name} onChange={set('child_first_name')} className={inputClass(form.errors.child_first_name)} required />
            </Field>
            <Field label="Child middle name" error={form.errors.child_middle_name}>
                <input value={form.data.child_middle_name} onChange={set('child_middle_name')} className={inputClass(form.errors.child_middle_name)} />
            </Field>
            <Field label="Child last name" error={form.errors.child_last_name}>
                <input value={form.data.child_last_name} onChange={set('child_last_name')} className={inputClass(form.errors.child_last_name)} required />
            </Field>
            <Field label="Date of birth" error={form.errors.date_of_birth}>
                <input type="date" max={today} value={form.data.date_of_birth} onChange={set('date_of_birth')} className={inputClass(form.errors.date_of_birth)} required />
            </Field>
            <Field label="Gender" error={form.errors.gender}>
                <select value={form.data.gender} onChange={set('gender')} className={inputClass(form.errors.gender)} required>
                    {GENDER_OPTIONS.map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                </select>
            </Field>
            <Field label="Class applying for" error={form.errors.applying_for}>
                <input value={form.data.applying_for} onChange={set('applying_for')} className={inputClass(form.errors.applying_for)} placeholder="Class 1" required />
            </Field>
            <Field label="Enquiry source" error={form.errors.source}>
                <select value={form.data.source} onChange={set('source')} className={inputClass(form.errors.source)}>
                    {SOURCE_OPTIONS.map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                </select>
            </Field>
            <Field label="Parent occupation" error={form.errors.parent_occupation}>
                <input value={form.data.parent_occupation} onChange={set('parent_occupation')} className={inputClass(form.errors.parent_occupation)} />
            </Field>
            <div className="sm:col-span-2 xl:col-span-3">
                <Field label="Child address" error={form.errors.child_address}>
                    <textarea rows={2} value={form.data.child_address} onChange={set('child_address')} className={inputClass(form.errors.child_address)} />
                </Field>
            </div>
            <div className="sm:col-span-2 xl:col-span-3">
                <Field label="Parent address" error={form.errors.parent_address}>
                    <textarea rows={2} value={form.data.parent_address} onChange={set('parent_address')} className={inputClass(form.errors.parent_address)} />
                </Field>
            </div>
            <div className="flex flex-wrap gap-3 sm:col-span-2 xl:col-span-3">
                <button disabled={form.processing} className="rounded-xl bg-teal-800 px-5 py-3 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-60">
                    {form.processing ? 'Saving…' : submitLabel}
                </button>
                <Link href={cancelHref} className="rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</Link>
            </div>
        </form>
    );
}