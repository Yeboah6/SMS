import { Link } from '@inertiajs/react';
import { Field, inputClass } from '../../Pages/Auth/Login';

/** Shared by FeeHeads/Create and FeeHeads/Edit (Edit passes isEditing to show the Active switch). */
export default function FeeHeadForm({ form, onSubmit, isEditing = false, cancelHref }) {
    return (
        <form onSubmit={onSubmit} className="grid gap-4 sm:grid-cols-2">
            <Field label="Code" error={form.errors.code}>
                <input value={form.data.code} onChange={(event) => form.setData('code', event.target.value.toUpperCase())} className={inputClass(form.errors.code)} maxLength={40} placeholder="TUITION" required />
            </Field>
            <Field label="Fee name" error={form.errors.name}>
                <input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} className={inputClass(form.errors.name)} maxLength={150} placeholder="Tuition fee" required />
            </Field>
            <div className="sm:col-span-2">
                <Field label="Description" error={form.errors.description}>
                    <input value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} className={inputClass(form.errors.description)} maxLength={2000} />
                </Field>
            </div>
            {isEditing && (
                <label className="flex items-start gap-3 text-sm text-slate-700 sm:col-span-2">
                    <input type="checkbox" checked={form.data.is_active} onChange={(event) => form.setData('is_active', event.target.checked)} className="mt-0.5 size-4 rounded border-slate-300 text-teal-700 focus:ring-teal-700" />
                    <span>Active<span className="block text-xs text-slate-500">Inactive fee heads cannot be added to new fee structures. Existing structures and invoices are not affected.</span></span>
                </label>
            )}
            <div className="flex flex-wrap gap-3 sm:col-span-2">
                <button type="submit" disabled={form.processing} className="rounded-xl bg-teal-800 px-5 py-3 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-60">{form.processing ? 'Saving…' : isEditing ? 'Save fee head' : 'Add fee head'}</button>
                <Link href={cancelHref} className="rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</Link>
            </div>
        </form>
    );
}