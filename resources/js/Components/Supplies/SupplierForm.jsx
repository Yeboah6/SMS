import { Link } from '@inertiajs/react';
import { Field, inputClass } from '../../Pages/Auth/Login';

export default function SupplierForm({ form, onSubmit, isEditing = false, cancelHref }) {
    const set = (key) => (event) => form.setData(key, event.target.value);

    return (
        <form onSubmit={onSubmit} className="grid gap-4 sm:grid-cols-2">
            <Field label="Supplier name" error={form.errors.name}><input value={form.data.name} onChange={set('name')} className={inputClass(form.errors.name)} maxLength={150} required /></Field>
            <Field label="Contact person" error={form.errors.contact_person}><input value={form.data.contact_person} onChange={set('contact_person')} className={inputClass(form.errors.contact_person)} maxLength={150} /></Field>
            <Field label="Phone" error={form.errors.phone}><input type="tel" value={form.data.phone} onChange={set('phone')} className={inputClass(form.errors.phone)} /></Field>
            <Field label="Email" error={form.errors.email}><input type="email" value={form.data.email} onChange={set('email')} className={inputClass(form.errors.email)} /></Field>
            <div className="sm:col-span-2"><Field label="Address" error={form.errors.address}><textarea rows={2} value={form.data.address} onChange={set('address')} className={inputClass(form.errors.address)} /></Field></div>
            <div className="sm:col-span-2"><Field label="Notes" error={form.errors.notes}><textarea rows={2} value={form.data.notes} onChange={set('notes')} className={inputClass(form.errors.notes)} /></Field></div>
            {isEditing && (
                <label className="flex items-center gap-3 text-sm text-slate-700 sm:col-span-2">
                    <input type="checkbox" checked={form.data.is_active} onChange={(event) => form.setData('is_active', event.target.checked)} className="size-4 rounded border-slate-300 text-teal-700 focus:ring-teal-700" />
                    Active (inactive suppliers cannot receive new purchases)
                </label>
            )}
            <div className="flex flex-wrap gap-3 sm:col-span-2">
                <button type="submit" disabled={form.processing} className="rounded-xl bg-teal-800 px-5 py-3 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-60">{form.processing ? 'Saving…' : isEditing ? 'Save changes' : 'Add supplier'}</button>
                <Link href={cancelHref} className="rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</Link>
            </div>
        </form>
    );
}