import { Link } from '@inertiajs/react';
import { Field, inputClass } from '../../Pages/Auth/Login';

/** Shared by Books/Create (adds the first copies) and Books/Edit (title details and availability). */
export default function BookForm({ form, onSubmit, isEditing = false, cancelHref }) {
    const set = (key) => (event) => form.setData(key, event.target.value);

    return (
        <form onSubmit={onSubmit} className="grid gap-4 sm:grid-cols-2">
            <Field label="Title" error={form.errors.title}>
                <input value={form.data.title} onChange={set('title')} className={inputClass(form.errors.title)} maxLength={200} required />
            </Field>
            <Field label="Author" error={form.errors.author}>
                <input value={form.data.author} onChange={set('author')} className={inputClass(form.errors.author)} maxLength={150} required />
            </Field>
            <Field label="ISBN" error={form.errors.isbn}>
                <input value={form.data.isbn} onChange={set('isbn')} className={inputClass(form.errors.isbn)} maxLength={30} />
            </Field>
            <Field label="Category" error={form.errors.category}>
                <input value={form.data.category} onChange={set('category')} className={inputClass(form.errors.category)} maxLength={100} required />
            </Field>
            <Field label="Publisher" error={form.errors.publisher}>
                <input value={form.data.publisher} onChange={set('publisher')} className={inputClass(form.errors.publisher)} maxLength={150} />
            </Field>
            {!isEditing && (
                <>
                    <Field label="Copies to add" error={form.errors.copies}>
                        <input type="number" min="1" max="100" value={form.data.copies} onChange={set('copies')} className={inputClass(form.errors.copies)} required />
                    </Field>
                    <Field label="Shelf location" error={form.errors.shelf_location}>
                        <input value={form.data.shelf_location} onChange={set('shelf_location')} className={inputClass(form.errors.shelf_location)} maxLength={100} />
                    </Field>
                </>
            )}
            <div className="sm:col-span-2">
                <Field label="Description" error={form.errors.description}>
                    <textarea rows={3} value={form.data.description} onChange={set('description')} className={inputClass(form.errors.description)} maxLength={4000} />
                </Field>
            </div>
            {isEditing && (
                <label className="flex items-start gap-3 text-sm text-slate-700 sm:col-span-2">
                    <input type="checkbox" checked={form.data.is_active} onChange={(event) => form.setData('is_active', event.target.checked)} className="mt-0.5 size-4 rounded border-slate-300 text-teal-700 focus:ring-teal-700" />
                    <span>Active in the catalogue<span className="block text-xs text-slate-500">Inactive titles are hidden from students and cannot be issued. Copies on loan must be returned first.</span></span>
                </label>
            )}
            {(form.errors.book || form.errors.is_active) && <p role="alert" className="text-sm text-rose-700 sm:col-span-2">{form.errors.book || form.errors.is_active}</p>}
            <div className="flex flex-wrap gap-3 sm:col-span-2">
                <button type="submit" disabled={form.processing} className="rounded-xl bg-teal-800 px-5 py-3 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-60">{form.processing ? 'Saving…' : isEditing ? 'Save changes' : 'Add book and copies'}</button>
                <Link href={cancelHref} className="rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</Link>
            </div>
        </form>
    );
}