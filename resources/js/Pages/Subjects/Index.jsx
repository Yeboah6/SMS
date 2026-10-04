import { Head, router, useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { Field, inputClass } from '../Auth/Login';

export default function SubjectsIndex({ subjects, canCreate }) {
    const { status } = usePage().props;
    const form = useForm({ code: '', name: '', description: '' });

    function submit(event) {
        event.preventDefault();
        form.post('/subjects', { onSuccess: () => form.reset() });
    }

    return (
        <AppLayout title="Subjects" description="Maintain the school's subject catalog for class and teacher assignments.">
            <Head title="Subjects" />
            {status && <div role="status" className="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{status}</div>}
            {canCreate && (
                <section className="mb-6 rounded-2xl border border-slate-200 bg-white p-5">
                    <h2 className="font-semibold text-slate-900">Add a subject</h2>
                    <form onSubmit={submit} className="mt-4 grid gap-4 sm:grid-cols-3">
                        <Field label="Subject code" error={form.errors.code}>
                            <input value={form.data.code} onChange={(event) => form.setData('code', event.target.value)} className={inputClass(form.errors.code)} placeholder="MATH" required />
                        </Field>
                        <Field label="Subject name" error={form.errors.name}>
                            <input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} className={inputClass(form.errors.name)} placeholder="Mathematics" required />
                        </Field>
                        <Field label="Description" error={form.errors.description}>
                            <input value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} className={inputClass(form.errors.description)} />
                        </Field>
                        <div className="sm:col-span-3">
                            <button disabled={form.processing} className="rounded-xl bg-teal-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-60">Create subject</button>
                        </div>
                    </form>
                </section>
            )}
            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div className="border-b border-slate-100 px-5 py-4">
                    <h2 className="font-semibold text-slate-900">Subject catalog</h2>
                    <p className="mt-1 text-sm text-slate-500">{subjects.total.toLocaleString()} subjects</p>
                </div>
                {subjects.data.length === 0 ? (
                    <p className="px-6 py-12 text-center text-sm text-slate-500">No subjects have been created.</p>
                ) : (
                    <div className="divide-y divide-slate-100">
                        {subjects.data.map((subject) => (
                            <div key={subject.id} className="flex items-center gap-4 px-5 py-4">
                                <span className="w-20 shrink-0 rounded-lg bg-slate-100 px-2 py-1 text-center font-mono text-xs font-semibold text-slate-600">{subject.code}</span>
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-medium text-slate-900">{subject.name}</p>
                                    {subject.description && <p className="mt-0.5 truncate text-xs text-slate-500">{subject.description}</p>}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
                {subjects.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-slate-100 px-5 py-3">
                        <button
                            type="button"
                            disabled={subjects.current_page <= 1}
                            onClick={() => router.get('/subjects', { page: subjects.current_page - 1 }, { preserveState: true, replace: true })}
                            className="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 disabled:opacity-40"
                        >
                            Previous
                        </button>
                        <span className="text-xs text-slate-500">Page {subjects.current_page} of {subjects.last_page}</span>
                        <button
                            type="button"
                            disabled={subjects.current_page >= subjects.last_page}
                            onClick={() => router.get('/subjects', { page: subjects.current_page + 1 }, { preserveState: true, replace: true })}
                            className="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 disabled:opacity-40"
                        >
                            Next
                        </button>
                    </div>
                )}
            </section>
        </AppLayout>
    );
}
