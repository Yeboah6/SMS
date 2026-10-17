import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import PageAlerts from '../../Components/Exams/PageAlerts';
import { AUDIENCES, STATE_LABELS, audienceLabel, dateLabel, dateTimeLabel, stateOf, stateTone } from '../../lib/notices';

const TABS = [['', 'All', 'all'], ['draft', 'Drafts', 'draft'], ['published', 'Published', 'published'], ['expired', 'Expired', 'expired']];

export default function NoticesIndex({ mode, notices, filters, counts, notifications, can }) {
    const [search, setSearch] = useState(filters.search || '');

    function visit(overrides = {}) {
        const params = { search, tab: filters.tab, audience: filters.audience, ...overrides };
        Object.keys(params).forEach((key) => !params[key] && delete params[key]);
        router.get('/notices', params, { preserveState: true, replace: true });
    }

    function post(url) {
        router.post(url, {}, { preserveScroll: true });
    }

    function deleteDraft(notice) {
        if (window.confirm(`Delete the draft “${notice.title}”?`)) {
            router.delete(`/notices/${notice.id}`, { preserveScroll: true });
        }
    }

    function withdraw(notice) {
        if (window.confirm(`Withdraw “${notice.title}” from the notice board? Notifications already sent are not recalled.`)) {
            post(`/notices/${notice.id}/withdraw`);
        }
    }

    return (
        <AppLayout title="Notices" description="School announcements and audience-specific updates.">
            <Head title="Notices" />
            <PageAlerts />

            {notifications.length > 0 && (
                <section className="mb-6 grid gap-3 rounded-2xl border border-teal-100 bg-teal-50/60 p-5">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 className="font-semibold text-slate-900">New notifications</h2>
                            <p className="mt-1 text-sm text-slate-600">Recent notice alerts sent to your account.</p>
                        </div>
                        <button type="button" onClick={() => post('/notifications/read-all')} className="rounded-lg border border-teal-200 bg-white px-3 py-2 text-sm font-medium text-teal-800 hover:bg-teal-50">Mark all read</button>
                    </div>
                    {notifications.map((notification) => (
                        <article key={notification.id} className="flex flex-wrap items-start justify-between gap-3 rounded-xl border border-teal-100 bg-white p-4">
                            <div>
                                <h3 className="font-semibold text-slate-900">{notification.noticeId ? <Link href={`/notices/${notification.noticeId}`} className="hover:text-teal-800">{notification.title}</Link> : notification.title}</h3>
                                <p className="mt-1 whitespace-pre-wrap text-sm text-slate-600">{notification.body}</p>
                            </div>
                            <button type="button" onClick={() => post(`/notifications/${notification.id}/read`)} className="rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Mark read</button>
                        </article>
                    ))}
                </section>
            )}

            {mode === 'manage' && (
                <nav aria-label="Notice status" className="mb-5 flex gap-2 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-2">
                    {TABS.map(([value, label, key]) => (
                        <button key={key} type="button" aria-pressed={filters.tab === value} onClick={() => visit({ tab: value })} className={`shrink-0 rounded-xl px-4 py-2.5 text-sm font-semibold ${filters.tab === value ? 'bg-teal-800 text-white' : 'text-slate-600 hover:bg-slate-100'}`}>
                            {label} <span className={filters.tab === value ? 'text-teal-100' : 'text-slate-400'}>{counts[key]}</span>
                        </button>
                    ))}
                </nav>
            )}

            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div className="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <h2 className="font-semibold text-slate-900">{mode === 'manage' ? `${notices.total} notice${notices.total === 1 ? '' : 's'}` : 'Notice board'}</h2>
                    <div className="flex flex-wrap items-center gap-3">
                        <form onSubmit={(event) => { event.preventDefault(); visit(); }} className="flex gap-2">
                            <input type="search" aria-label="Search notices" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search notices" className="w-48 rounded-xl border border-slate-200 px-3 py-2 text-sm" />
                            <button className="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Search</button>
                        </form>
                        {mode === 'manage' && (
                            <select aria-label="Audience" value={filters.audience} onChange={(event) => visit({ audience: event.target.value })} className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                                <option value="">All audiences</option>
                                {AUDIENCES.map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                            </select>
                        )}
                        {can.create && <Link href="/notices/create" className="rounded-xl bg-teal-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-900">New notice</Link>}
                    </div>
                </div>

                {notices.data.length === 0 ? (
                    <p className="px-6 py-14 text-center text-sm text-slate-500">No notices are available.</p>
                ) : mode === 'manage' ? (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[860px] text-left text-sm">
                            <thead className="bg-slate-50 text-xs text-slate-500">
                                <tr><th className="px-5 py-3">Notice</th><th className="px-5 py-3">Audience</th><th className="px-5 py-3">State</th><th className="px-5 py-3">Published</th><th className="px-5 py-3">Expires</th><th className="px-5 py-3"><span className="sr-only">Actions</span></th></tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {notices.data.map((notice) => {
                                    const state = stateOf(notice);
                                    return (
                                        <tr key={notice.id} className="hover:bg-slate-50/70">
                                            <td className="px-5 py-3.5"><Link href={`/notices/${notice.id}`} className="font-medium text-teal-800 hover:text-teal-950">{notice.title}</Link><span className="block text-xs text-slate-500">{notice.createdBy}</span></td>
                                            <td className="px-5 py-3.5 text-slate-600">{audienceLabel(notice.audience)}</td>
                                            <td className="px-5 py-3.5"><span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${stateTone(state)}`}>{STATE_LABELS[state]}</span></td>
                                            <td className="px-5 py-3.5 text-slate-600">{dateTimeLabel(notice.publishedAt) || '—'}</td>
                                            <td className="px-5 py-3.5 text-slate-600">{dateLabel(notice.expiresAt) || '—'}</td>
                                            <td className="px-5 py-3.5">
                                                <div className="flex items-center justify-end gap-3 text-xs font-semibold">
                                                    {notice.canPublish && <button type="button" onClick={() => post(`/notices/${notice.id}/publish`)} className="rounded-lg bg-teal-700 px-3 py-1.5 text-white hover:bg-teal-800">Publish</button>}
                                                    <Link href={`/notices/${notice.id}`} className="text-teal-800 hover:text-teal-950">View</Link>
                                                    {notice.canEdit && <Link href={`/notices/${notice.id}/edit`} className="text-teal-800 hover:text-teal-950">Edit</Link>}
                                                    {notice.canDelete && <button type="button" onClick={() => deleteDraft(notice)} className="text-rose-700 hover:text-rose-900">Delete</button>}
                                                    {notice.canWithdraw && <button type="button" onClick={() => withdraw(notice)} className="text-rose-700 hover:text-rose-900">Withdraw</button>}
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <div className="grid gap-3 p-5">
                        {notices.data.map((notice) => (
                            <article key={notice.id} className="rounded-2xl border border-slate-200 p-5">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <h3 className="font-semibold text-slate-900"><Link href={`/notices/${notice.id}`} className="hover:text-teal-800">{notice.title}</Link></h3>
                                        <p className="mt-1 text-xs text-slate-500">{notice.status === 'draft' ? 'Draft' : `Published ${dateTimeLabel(notice.publishedAt)}`} · {notice.createdBy} · {audienceLabel(notice.audience)}{notice.expiresAt ? ` · Expires ${dateLabel(notice.expiresAt)}` : ''}</p>
                                    </div>
                                    {notice.canEdit && <Link href={`/notices/${notice.id}/edit`} className="text-xs font-semibold text-teal-800 hover:text-teal-950">Edit draft</Link>}
                                </div>
                                <p className="mt-3 whitespace-pre-wrap text-sm leading-6 text-slate-700">{notice.excerpt}</p>
                                <Link href={`/notices/${notice.id}`} className="mt-3 inline-block text-sm font-semibold text-teal-800 hover:text-teal-950">Read notice →</Link>
                            </article>
                        ))}
                    </div>
                )}

                {notices.links.length > 3 && (
                    <nav aria-label="Notice pages" className="flex flex-wrap gap-2 border-t border-slate-100 px-5 py-4">
                        {notices.links.map((link, index) => (
                            <Link key={`${link.label}-${index}`} href={link.url || '#'} preserveScroll className={`rounded-lg px-3 py-2 text-sm ${link.active ? 'bg-teal-700 text-white' : 'border border-slate-200 bg-white text-slate-700'} ${!link.url ? 'pointer-events-none opacity-50' : ''}`}>
                                {link.label.replace('&laquo;', '‹').replace('&raquo;', '›')}
                            </Link>
                        ))}
                    </nav>
                )}
            </section>
        </AppLayout>
    );
}