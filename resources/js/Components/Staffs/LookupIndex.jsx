import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import PageAlerts from '../Exams/PageAlerts';
import Pagination from '../Pagination';
import { Field, inputClass } from '../../Pages/Auth/Login';

/** One page for a small list: add at the top, rename in place, delete when nobody is assigned. */
export default function LookupIndex({ title, description, noun, baseUrl, items, unassigned }) {
    const [editing, setEditing] = useState(null);
    const itemRows = items.data || [];
    const plural = noun.endsWith('y') ? `${noun.slice(0, -1)}ies` : `${noun}s`;

    function remove(item) {
        if (window.confirm(`Delete the ${noun} “${item.name}”?`)) {
            router.delete(`${baseUrl}/${item.id}`, { preserveScroll: true });
        }
    }

    return (
        <AppLayout title={title} description={description}>
            <Head title={title} />
            <PageAlerts />

            <div className="mb-6"><Link href="/staff" className="text-sm font-semibold text-teal-800 hover:text-teal-950">Back to staff</Link></div>

            <section className="mb-6 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <h2 className="mb-4 font-semibold text-slate-900">Add a {noun}</h2>
                <NameForm action={baseUrl} method="post" noun={noun} />
            </section>

            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                    <h2 className="font-semibold text-slate-900">{items.total} {items.total === 1 ? noun : plural}</h2>
                    {unassigned > 0 && <p className="text-xs text-slate-500">{unassigned} staff member{unassigned === 1 ? ' has' : 's have'} no {noun} yet</p>}
                </div>
                {itemRows.length === 0 ? (
                    <p className="px-6 py-12 text-center text-sm text-slate-500">Nothing here yet. Add the first {noun} above.</p>
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {itemRows.map((item) => (
                            <li key={item.id} className="px-5 py-4">
                                {editing === item.id ? (
                                    <NameForm action={`${baseUrl}/${item.id}`} method="put" noun={noun} item={item} onDone={() => setEditing(null)} />
                                ) : (
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <div>
                                            <Link href={`${baseUrl}/${item.id}`} className="font-medium text-slate-900 hover:text-teal-800">{item.name}</Link>
                                            <p className="mt-0.5 text-xs text-slate-500">{item.staffCount} staff member{item.staffCount === 1 ? '' : 's'}</p>
                                        </div>
                                        <div className="flex items-center gap-4 text-xs font-semibold">
                                            <Link href={`${baseUrl}/${item.id}`} className="text-teal-800 hover:text-teal-950">View staff</Link>
                                            <button type="button" onClick={() => setEditing(item.id)} className="text-teal-800 hover:text-teal-950">Rename</button>
                                            {item.staffCount === 0 && <button type="button" onClick={() => remove(item)} className="text-rose-700 hover:text-rose-900">Delete</button>}
                                        </div>
                                    </div>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
                <Pagination links={items.links} from={items.from} to={items.to} total={items.total} />
            </section>
        </AppLayout>
    );
}

function NameForm({ action, method, noun, item = null, onDone = null }) {
    const form = useForm({ name: item?.name || '' });

    function submit(event) {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => { form.reset(); onDone?.(); } };
        if (method === 'put') form.put(action, options); else form.post(action, options);
    }

    return (
        <>
            <PageAlerts errors={form.errors} />
            <form onSubmit={submit} className="flex flex-wrap items-end gap-3">
                <div className="min-w-60 flex-1">
                    <Field label={`${noun[0].toUpperCase()}${noun.slice(1)} name`} error={form.errors.name}>
                        <input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} className={inputClass(form.errors.name)} maxLength={100} required />
                    </Field>
                </div>
                <button disabled={form.processing} className="rounded-xl bg-teal-800 px-5 py-3 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-60">{item ? 'Save' : `Add ${noun}`}</button>
                {onDone && <button type="button" onClick={onDone} className="rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>}
            </form>
        </>
    );
}