import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../../Layouts/AppLayout';
import PageAlerts from '../../../Components/PageAlerts';
import SuppliesNav from '../../../Components/Supplies/SuppliesNav';
import { Field, inputClass } from '../../Auth/Login';

export default function CategoriesIndex({ categories, can }) {
    const [editing, setEditing] = useState(null);

    return (
        <AppLayout title="Item categories" description="Exercise books, notebooks, textbooks, stationery and anything else you sell.">
            <Head title="Item categories" />
            <PageAlerts />
            <SuppliesNav current="items" />
            <div className="mb-6"><Link href="/supplies/items" className="text-sm font-semibold text-teal-800 hover:text-teal-950">Back to inventory</Link></div>

            {can.manage && <section className="mb-6 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6"><h2 className="mb-4 font-semibold text-slate-900">Add a category</h2><CategoryForm action="/supplies/categories" method="post" /></section>}

            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <ul className="divide-y divide-slate-100">
                    {categories.map((category) => (
                        <li key={category.id} className="px-5 py-4">
                            {editing === category.id ? (
                                <CategoryForm action={`/supplies/categories/${category.id}`} method="put" category={category} onDone={() => setEditing(null)} />
                            ) : (
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <div><p className="font-medium text-slate-900">{category.name}{!category.isActive && <span className="ml-2 rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600">Inactive</span>}</p><p className="mt-0.5 text-xs text-slate-500">{category.items} item{category.items === 1 ? '' : 's'}{category.description ? ` · ${category.description}` : ''}</p></div>
                                    {can.manage && (
                                        <div className="flex items-center gap-3 text-xs font-semibold">
                                            <button type="button" onClick={() => setEditing(category.id)} className="text-teal-800 hover:text-teal-950">Edit</button>
                                            {category.items === 0 && <button type="button" onClick={() => window.confirm(`Delete ${category.name}?`) && router.delete(`/supplies/categories/${category.id}`, { preserveScroll: true })} className="text-rose-700 hover:text-rose-900">Delete</button>}
                                        </div>
                                    )}
                                </div>
                            )}
                        </li>
                    ))}
                </ul>
            </section>
        </AppLayout>
    );
}

function CategoryForm({ action, method, category = null, onDone = null }) {
    const form = useForm({ name: category?.name || '', description: category?.description || '', is_active: category?.isActive ?? true });

    function submit(event) {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => { form.reset(); onDone?.(); } };
        if (method === 'put') form.put(action, options); else form.post(action, options);
    }

    return (
        <form onSubmit={submit} className="grid gap-3 sm:grid-cols-[1fr_2fr_auto_auto] sm:items-end">
            <Field label="Name" error={form.errors.name}><input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} className={inputClass(form.errors.name)} maxLength={100} required /></Field>
            <Field label="Description" error={form.errors.description}><input value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} className={inputClass(form.errors.description)} /></Field>
            <label className="flex items-center gap-2 pb-3 text-sm text-slate-700"><input type="checkbox" checked={form.data.is_active} onChange={(event) => form.setData('is_active', event.target.checked)} className="size-4 rounded border-slate-300 text-teal-700" />Active</label>
            <div className="flex gap-2 pb-0.5"><button disabled={form.processing} className="rounded-xl bg-teal-800 px-4 py-3 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-60">{category ? 'Save' : 'Add'}</button>{onDone && <button type="button" onClick={onDone} className="rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700">Cancel</button>}</div>
        </form>
    );
}