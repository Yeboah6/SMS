import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, LockKeyhole } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import { Field, inputClass } from '../Auth/Login';
import { formatDate, StatusBadge } from './Index';

export default function AcademicYearShow({ year, can }) {
    const { status, errors } = usePage().props;
    const yearForm = useForm({
        name: year.name,
        start_date: year.startDate,
        end_date: year.endDate,
    });
    const termForm = useForm({ name: '', start_date: '', end_date: '' });

    function updateYear(event) {
        event.preventDefault();
        yearForm.patch(`/academic-years/${year.id}`);
    }

    function createTerm(event) {
        event.preventDefault();
        termForm.post(`/academic-years/${year.id}/terms`, { onSuccess: () => termForm.reset() });
    }

    function transition(action) {
        const message = {
            activate: 'Activate this academic year?',
            complete: 'Complete this academic year?',
            lock: 'Lock this year? Existing academic transactions will become read-only.',
        }[action];

        if (window.confirm(message)) {
            router.post(`/academic-years/${year.id}/${action}`);
        }
    }

    return (
        <AppLayout title={year.name} description="Review this academic year's dates, terms, and lifecycle state.">
            <Head title={year.name} />
            {status && <div role="status" className="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{status}</div>}
            {errors.academic_year && <div role="alert" className="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{errors.academic_year}</div>}

            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <Link href="/academic-years" className="inline-flex items-center gap-2 text-sm font-medium text-slate-600 hover:text-teal-800">
                    <ArrowLeft size={16} />
                    All academic years
                </Link>
                <div className="flex items-center gap-2">
                    <StatusBadge status={year.status} />
                    {year.status === 'draft' && can.activate && <LifecycleButton onClick={() => transition('activate')}>Activate year</LifecycleButton>}
                    {year.status === 'active' && can.complete && <LifecycleButton onClick={() => transition('complete')}>Complete year</LifecycleButton>}
                    {year.status === 'completed' && can.lock && <LifecycleButton onClick={() => transition('lock')} icon>Lock year</LifecycleButton>}
                </div>
            </div>

            {year.status === 'locked' && (
                <div className="mb-6 flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                    <LockKeyhole size={18} className="mt-0.5 shrink-0" />
                    <p>This year is locked. Historical information remains available, but academic changes are disabled.</p>
                </div>
            )}

            <div className="grid gap-6 xl:grid-cols-2">
                <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <h2 className="font-semibold text-slate-900">Year dates</h2>
                    {year.status === 'draft' && can.update ? (
                        <form onSubmit={updateYear} className="mt-5 space-y-4">
                            <Field label="Academic year name" error={yearForm.errors.name}>
                                <input value={yearForm.data.name} onChange={(event) => yearForm.setData('name', event.target.value)} className={inputClass(yearForm.errors.name)} required />
                            </Field>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field label="Start date" error={yearForm.errors.start_date}>
                                    <input type="date" value={yearForm.data.start_date} onChange={(event) => yearForm.setData('start_date', event.target.value)} className={inputClass(yearForm.errors.start_date)} required />
                                </Field>
                                <Field label="End date" error={yearForm.errors.end_date}>
                                    <input type="date" value={yearForm.data.end_date} onChange={(event) => yearForm.setData('end_date', event.target.value)} className={inputClass(yearForm.errors.end_date)} required />
                                </Field>
                            </div>
                            <button disabled={yearForm.processing} className="rounded-xl bg-teal-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-60">Save dates</button>
                        </form>
                    ) : (
                        <dl className="mt-5 grid gap-4 sm:grid-cols-2">
                            <DateItem label="Starts" value={formatDate(year.startDate)} />
                            <DateItem label="Ends" value={formatDate(year.endDate)} />
                        </dl>
                    )}
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <h2 className="font-semibold text-slate-900">Terms</h2>
                            <p className="mt-1 text-sm text-slate-500">{year.terms.length} terms configured</p>
                        </div>
                    </div>
                    {year.terms.length ? (
                        <div className="mt-4 divide-y divide-slate-100">
                            {year.terms.map((term) => (
                                <div key={term.id} className="flex flex-wrap items-center justify-between gap-2 py-3">
                                    <p className="text-sm font-medium text-slate-800">{term.name}</p>
                                    <p className="text-xs text-slate-500">{formatDate(term.startDate)} – {formatDate(term.endDate)}</p>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <p className="mt-5 rounded-xl bg-slate-50 px-4 py-5 text-sm text-slate-500">No terms have been added yet.</p>
                    )}
                    {year.status === 'draft' && can.update && (
                        <form onSubmit={createTerm} className="mt-5 grid gap-3 border-t border-slate-100 pt-5">
                            <Field label="Term name" error={termForm.errors.name}>
                                <input value={termForm.data.name} onChange={(event) => termForm.setData('name', event.target.value)} className={inputClass(termForm.errors.name)} placeholder="Term 1" required />
                            </Field>
                            <div className="grid gap-3 sm:grid-cols-2">
                                <Field label="Start date" error={termForm.errors.start_date}>
                                    <input type="date" value={termForm.data.start_date} onChange={(event) => termForm.setData('start_date', event.target.value)} className={inputClass(termForm.errors.start_date)} required />
                                </Field>
                                <Field label="End date" error={termForm.errors.end_date}>
                                    <input type="date" value={termForm.data.end_date} onChange={(event) => termForm.setData('end_date', event.target.value)} className={inputClass(termForm.errors.end_date)} required />
                                </Field>
                            </div>
                            <button disabled={termForm.processing} className="w-fit rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-60">Add term</button>
                        </form>
                    )}
                    {termForm.errors.academic_year && <p role="alert" className="mt-3 text-sm text-rose-700">{termForm.errors.academic_year}</p>}
                </section>
            </div>
        </AppLayout>
    );
}

function DateItem({ label, value }) {
    return (
        <div>
            <dt className="text-xs font-medium uppercase tracking-wide text-slate-500">{label}</dt>
            <dd className="mt-1 text-sm font-semibold text-slate-900">{value}</dd>
        </div>
    );
}

function LifecycleButton({ onClick, children, icon = false }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className="inline-flex items-center gap-1.5 rounded-xl bg-teal-800 px-3.5 py-2 text-sm font-semibold text-white hover:bg-teal-900"
        >
            {icon && <LockKeyhole size={15} />}
            {children}
        </button>
    );
}
