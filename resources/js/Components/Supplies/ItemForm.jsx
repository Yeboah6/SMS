import { Link } from '@inertiajs/react';
import { Field, inputClass } from '../../Pages/Auth/Login';

/** Shared by Items/Create (adds opening stock) and Items/Edit (stock is changed on the Adjust page, never here). */
export default function ItemForm({ form, onSubmit, categories, isEditing = false, cancelHref }) {
    const set = (key) => (event) => form.setData(key, event.target.value);

    return (
        <form onSubmit={onSubmit} className="grid gap-4 sm:grid-cols-2">
            <Field label="Item name" error={form.errors.name}>
                <input value={form.data.name} onChange={set('name')} className={inputClass(form.errors.name)} maxLength={200} required />
            </Field>
            <div>
                <Field label={isEditing ? 'SKU / code' : 'SKU / code (optional)'} error={form.errors.sku}>
                    <input value={form.data.sku} onChange={set('sku')} className={inputClass(form.errors.sku)} maxLength={60} required={isEditing} />
                </Field>
                {!isEditing && <p className="mt-1.5 text-xs text-slate-500">Leave blank to generate one (ITM-000123).</p>}
            </div>
            <Field label="Category" error={form.errors.category_id}>
                <select value={form.data.category_id} onChange={set('category_id')} className={inputClass(form.errors.category_id)} required>
                    <option value="">Select category</option>
                    {categories.map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}
                </select>
            </Field>
            <Field label="Unit" error={form.errors.unit}>
                <input value={form.data.unit} onChange={set('unit')} className={inputClass(form.errors.unit)} maxLength={30} placeholder="piece, pack, ream" required />
            </Field>
            <Field label="Cost price" error={form.errors.cost_price}>
                <input type="number" min="0" step="0.01" value={form.data.cost_price} onChange={set('cost_price')} className={inputClass(form.errors.cost_price)} required />
            </Field>
            <Field label="Selling price" error={form.errors.selling_price}>
                <input type="number" min="0" step="0.01" value={form.data.selling_price} onChange={set('selling_price')} className={inputClass(form.errors.selling_price)} required />
            </Field>
            <Field label="Reorder level" error={form.errors.reorder_level}>
                <input type="number" min="0" value={form.data.reorder_level} onChange={set('reorder_level')} className={inputClass(form.errors.reorder_level)} required />
            </Field>
            {!isEditing && (
                <Field label="Opening stock" error={form.errors.opening_quantity}>
                    <input type="number" min="0" value={form.data.opening_quantity} onChange={set('opening_quantity')} className={inputClass(form.errors.opening_quantity)} />
                </Field>
            )}
            <div className="sm:col-span-2">
                <Field label="Description" error={form.errors.description}>
                    <textarea rows={3} value={form.data.description} onChange={set('description')} className={inputClass(form.errors.description)} maxLength={2000} />
                </Field>
            </div>
            <label className="flex items-start gap-3 text-sm text-slate-700 sm:col-span-2">
                <input type="checkbox" checked={form.data.is_active} onChange={(event) => form.setData('is_active', event.target.checked)} className="mt-0.5 size-4 rounded border-slate-300 text-teal-700 focus:ring-teal-700" />
                <span>Active<span className="block text-xs text-slate-500">Inactive items cannot be sold or added to new requirements. History is kept.</span></span>
            </label>
            <div className="flex flex-wrap gap-3 sm:col-span-2">
                <button type="submit" disabled={form.processing} className="rounded-xl bg-teal-800 px-5 py-3 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-60">{form.processing ? 'Saving…' : isEditing ? 'Save changes' : 'Add item'}</button>
                <Link href={cancelHref} className="rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</Link>
            </div>
        </form>
    );
}