import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { CalendarDays, Check, LockKeyhole, Plus } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import { Field, inputClass } from '../Auth/Login';

export default function AcademicYearsIndex({ years, can }) {
    const { status, errors } = usePage().props;
    const form = useForm({ name: '', start_date: '', end_date: '' });

    function createYear(event) {
        event.preventDefault();
        form.post('/academic-years', { onSuccess: () => form.reset() });
    }

    function runAction(year, action) {
        const labels = {
            activate: 'Activate this academic year?',
            complete: 'Complete this academic year? It will no longer be the active year.',
            lock: 'Lock this academic year? Its transactions will become read-only.',
            delete: 'Permanently delete this draft academic year?',
        };

        if (!window.confirm(labels[action])) {
            return;
        }

        if (action === 'delete') {
            router.delete(`/academic-years/${year.id}`, { preserveScroll: true });
            return;
        }

        router.post(`/academic-years/${year.id}/${action}`, { preserveScroll: true });
    }

    return (
        <AppLayout title="Academic years" description="Manage school-year history and control when academic transactions can change.">
            <Head title="Academic years" />
            {status && <StatusMessage>{status}</StatusMessage>}
            {errors.academic_year && <ErrorMessage>{errors.academic_year}</ErrorMessage>}

            {can.create && (
                <section className="mb-7 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <div className="mb-5 flex items-start gap-3">
                        <span className="flex size-10 items-center justify-center rounded-xl bg-teal-50 text-teal-800">
                            <Plus size={19} />
                        </span>
                        <div>
                            <h2 className="font-semibold text-slate-900">Create an academic year</h2>
                            <p className="mt-1 text-sm text-slate-500">New years start in Draft. Add terms before activation.</p>
                        </div>
                    </div>
                    <form onSubmit={createYear} className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <Field label="Academic year name" error={form.errors.name}>
                            <input
                                value={form.data.name}
                                onChange={(event) => form.setData('name', event.target.value)}
                                className={inputClass(form.errors.name)}
                                placeholder="2026/2027"
                                required
                            />
                        </Field>
                        <Field label="Start date" error={form.errors.start_date}>
                            <input
                                type="date"
                                value={form.data.start_date}
                                onChange={(event) => form.setData('start_date', event.target.value)}
                                className={inputClass(form.errors.start_date)}
                                required
                            />
                        </Field>
                        <Field label="End date" error={form.errors.end_date}>
                            <input
                                type="date"
                                value={form.data.end_date}
                                onChange={(event) => form.setData('end_date', event.target.value)}
                                className={inputClass(form.errors.end_date)}
                                required
                            />
                        </Field>
                        <div className="flex items-end">
                            <button
                                disabled={form.processing}
                                className="w-full rounded-xl bg-teal-800 px-4 py-3 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-60"
                            >
                                Create draft
                            </button>
                        </div>
                    </form>
                </section>
            )}

            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div className="flex items-center justify-between border-b border-slate-100 px-5 py-5 sm:px-6">
                    <div>
                        <h2 className="font-semibold text-slate-900">Year history</h2>
                        <p className="mt-1 text-sm text-slate-500">{years.total.toLocaleString()} academic years</p>
                    </div>
                </div>
                {years.data.length === 0 ? (
                    <div className="px-6 py-14 text-center">
                        <CalendarDays className="mx-auto text-slate-400" size={24} />
                        <p className="mt-3 font-medium text-slate-800">No academic years yet</p>
                        <p className="mt-1 text-sm text-slate-500">Create a draft to begin setting up the school calendar.</p>
                    </div>
                ) : (
                    <div className="divide-y divide-slate-100">
                        {years.data.map((year) => (
                            <article key={year.id} className="flex flex-col gap-4 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                                <div className="min-w-0">
                                    <div className="flex flex-wrap items-center gap-2.5">
                                        <Link href={`/academic-years/${year.id}`} className="text-base font-semibold text-slate-900 hover:text-teal-800">
                                            {year.name}
                                        </Link>
                                        <StatusBadge status={year.status} />
                                    </div>
                                    <p className="mt-1.5 text-sm text-slate-500">
                                        {formatDate(year.startDate)} – {formatDate(year.endDate)}
                                        <span className="px-2 text-slate-300">·</span>
                                        {year.termCount} {year.termCount === 1 ? 'term' : 'terms'}
                                    </p>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    {year.status === 'draft' && can.activate && (
                                        <ActionButton onClick={() => runAction(year, 'activate')} icon={Check}>Activate</ActionButton>
                                    )}
                                    {year.status === 'active' && can.complete && (
                                        <ActionButton onClick={() => runAction(year, 'complete')}>Complete</ActionButton>
                                    )}
                                    {year.status === 'completed' && can.lock && (
                                        <ActionButton onClick={() => runAction(year, 'lock')} icon={LockKeyhole}>Lock year</ActionButton>
                                    )}
                                    {year.status === 'draft' && can.delete && (
                                        <ActionButton onClick={() => runAction(year, 'delete')} destructive>Delete</ActionButton>
                                    )}
                                </div>
                            </article>
                        ))}
                    </div>
                )}
                <Pagination links={years.links} />
            </section>
        </AppLayout>
    );
}

export function StatusBadge({ status }) {
    const styles = {
        draft: 'bg-slate-100 text-slate-700',
        active: 'bg-emerald-50 text-emerald-800',
        completed: 'bg-blue-50 text-blue-800',
        locked: 'bg-amber-50 text-amber-800',
    };

    return <span className={`rounded-full px-2.5 py-1 text-xs font-semibold capitalize ${styles[status] || styles.draft}`}>{status}</span>;
}

export function formatDate(value) {
    if (!value) {
        return '';
    }

    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(`${value}T12:00:00`));
}

function ActionButton({ children, onClick, icon: Icon, destructive = false }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`inline-flex items-center gap-1.5 rounded-lg border px-3 py-2 text-xs font-semibold ${
                destructive
                    ? 'border-rose-200 text-rose-700 hover:bg-rose-50'
                    : 'border-slate-200 text-slate-700 hover:bg-slate-50'
            }`}
        >
            {Icon && <Icon size={14} />}
            {children}
        </button>
    );
}

function Pagination({ links }) {
    if (links.length <= 3) {
        return null;
    }

    return (
        <nav aria-label="Academic year pagination" className="flex flex-wrap justify-center gap-1 border-t border-slate-100 px-4 py-4">
            {links.map((link, index) => (
                <button
                    key={`${link.label}-${index}`}
                    type="button"
                    disabled={!link.url}
                    onClick={() => link.url && router.visit(link.url, { preserveScroll: true })}
                    className={`rounded-lg px-3 py-2 text-sm ${
                        link.active ? 'bg-teal-800 font-semibold text-white' : 'text-slate-600 hover:bg-slate-100 disabled:text-slate-300'
                    }`}
                    aria-current={link.active ? 'page' : undefined}
                >
                    {link.label.replace(/<[^>]*>/g, '').replaceAll('&laquo;', '‹').replaceAll('&raquo;', '›')}
                </button>
            ))}
        </nav>
    );
}

function StatusMessage({ children }) {
    return <div role="status" className="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{children}</div>;
}

function ErrorMessage({ children }) {
    return <div role="alert" className="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{children}</div>;
}
