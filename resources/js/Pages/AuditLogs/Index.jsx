import { Head, Link, router } from '@inertiajs/react';
import { ClipboardList, Search } from 'lucide-react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';

export default function AuditLogsIndex({ logs, actions, filters }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [action, setAction] = useState(filters.action ?? '');

    function applyFilters(event) {
        event.preventDefault();
        router.get('/audit-logs', { search, action }, { preserveState: true, replace: true });
    }

    return (
        <AppLayout title="Audit log" description="Review administrative and academic changes recorded in the system.">
            <Head title="Audit log" />

            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <form onSubmit={applyFilters} className="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-end sm:p-5">
                    <label className="flex-1 space-y-1.5">
                        <span className="text-xs font-medium text-slate-600">Search activity</span>
                        <span className="flex items-center gap-2 rounded-lg border border-slate-200 px-3 focus-within:border-teal-700">
                            <Search size={16} className="text-slate-400" aria-hidden="true" />
                            <input
                                type="search"
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                maxLength={100}
                                placeholder="Actor, student, or activity"
                                className="w-full border-0 py-2.5 text-sm outline-none focus:ring-0"
                            />
                        </span>
                    </label>
                    <label className="space-y-1.5 sm:min-w-56">
                        <span className="block text-xs font-medium text-slate-600">Activity type</span>
                        <select
                            value={action}
                            onChange={(event) => setAction(event.target.value)}
                            className="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:border-teal-700 focus:outline-none"
                        >
                            <option value="">All activity types</option>
                            {actions.map((item) => <option key={item} value={item}>{formatAction(item)}</option>)}
                        </select>
                    </label>
                    <button
                        type="submit"
                        className="rounded-lg bg-teal-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-900"
                    >
                        Apply filters
                    </button>
                </form>

                {logs.data.length > 0 ? (
                    <div className="divide-y divide-slate-100">
                        {logs.data.map((log) => (
                            <article key={log.id} className="flex flex-col gap-3 px-4 py-4 sm:px-5 lg:flex-row lg:items-start lg:justify-between">
                                <div className="min-w-0 space-y-1.5">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="rounded-full bg-teal-50 px-2.5 py-1 text-[11px] font-semibold text-teal-800">
                                            {formatAction(log.action)}
                                        </span>
                                        <span className="text-xs text-slate-500">{log.subjectType}</span>
                                    </div>
                                    <p className="text-sm font-medium text-slate-900">{log.summary}</p>
                                    <p className="text-xs text-slate-600">
                                        <span className="font-medium">{log.actorName}</span>
                                        {log.subjectName && <> · {log.subjectName}</>}
                                    </p>
                                    {log.changedFields.length > 0 && (
                                        <div className="flex flex-wrap gap-1.5 pt-1" aria-label="Changed fields">
                                            {log.changedFields.map((field) => (
                                                <span key={field} className="rounded-md bg-slate-100 px-2 py-1 text-[11px] text-slate-600">
                                                    {field}
                                                </span>
                                            ))}
                                        </div>
                                    )}
                                </div>
                                <time
                                    dateTime={log.occurredAt}
                                    className="shrink-0 text-xs text-slate-500"
                                >
                                    {new Date(log.occurredAt).toLocaleString()}
                                </time>
                            </article>
                        ))}
                    </div>
                ) : (
                    <div className="flex flex-col items-center gap-2 px-6 py-14 text-center">
                        <ClipboardList size={28} className="text-slate-400" aria-hidden="true" />
                        <p className="text-sm font-medium text-slate-800">No audit activity found</p>
                        <p className="text-sm text-slate-500">Try changing or clearing the filters.</p>
                    </div>
                )}

                {logs.last_page > 1 && (
                    <nav aria-label="Audit log pages" className="flex flex-col gap-3 border-t border-slate-100 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <p className="text-xs text-slate-500">
                            Showing {logs.from}–{logs.to} of {logs.total} entries
                        </p>
                        <div className="flex flex-wrap gap-1.5">
                            {logs.links.map((page, index) => {
                                const label = page.label.replace('&laquo;', 'Previous').replace('&raquo;', 'Next');
                                const classes = `rounded-lg border px-3 py-2 text-xs ${
                                    page.active
                                        ? 'border-teal-800 bg-teal-800 font-semibold text-white'
                                        : 'border-slate-200 text-slate-700 hover:bg-slate-50'
                                } ${page.url ? '' : 'pointer-events-none opacity-50'}`;

                                return page.url ? (
                                    <Link key={`${page.label}-${index}`} href={page.url} preserveScroll className={classes}>
                                        {label}
                                    </Link>
                                ) : (
                                    <span key={`${page.label}-${index}`} className={classes}>{label}</span>
                                );
                            })}
                        </div>
                    </nav>
                )}
            </section>
        </AppLayout>
    );
}

function formatAction(action) {
    return action
        .split(/[._]/)
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}
