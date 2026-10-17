import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import PageAlerts from '../../Components/Exams/PageAlerts';
import { STATE_LABELS, audienceLabel, dateLabel, dateTimeLabel, stateOf, stateTone } from '../../lib/notices';

export default function NoticesShow({ notice, showDetails, can }) {
    const state = stateOf(notice);

    function post(url) {
        router.post(url, {}, { preserveScroll: true });
    }

    function deleteDraft() {
        if (window.confirm(`Delete the draft “${notice.title}”?`)) {
            router.delete(`/notices/${notice.id}`);
        }
    }

    function withdraw() {
        if (window.confirm('Withdraw this notice from the notice board? Notifications already sent are not recalled.')) {
            post(`/notices/${notice.id}/withdraw`);
        }
    }

    return (
        <AppLayout title={notice.title} description={`${audienceLabel(notice.audience)} · ${notice.createdBy}`}>
            <Head title={notice.title} />
            <PageAlerts />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <Link href="/notices" className="text-sm font-semibold text-teal-800 hover:text-teal-950">Back to notices</Link>
                <div className="flex flex-wrap items-center gap-3 text-sm font-semibold">
                    {can.publish && <button type="button" onClick={() => post(`/notices/${notice.id}/publish`)} className="rounded-lg bg-teal-700 px-3 py-2 text-white hover:bg-teal-800">Publish</button>}
                    {can.edit && <Link href={`/notices/${notice.id}/edit`} className="text-teal-800 hover:text-teal-950">Edit draft</Link>}
                    {can.delete && <button type="button" onClick={deleteDraft} className="text-rose-700 hover:text-rose-900">Delete</button>}
                    {can.withdraw && <button type="button" onClick={withdraw} className="text-rose-700 hover:text-rose-900">Withdraw</button>}
                </div>
            </div>

            <article className="max-w-3xl rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
                <div className="flex flex-wrap items-center gap-3">
                    <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${stateTone(state)}`}>{STATE_LABELS[state]}</span>
                    <p className="text-xs text-slate-500">
                        {notice.status === 'draft' ? `Created ${dateTimeLabel(notice.createdAt)}` : `Published ${dateTimeLabel(notice.publishedAt)}`}
                        {notice.expiresAt ? ` · ${notice.expired ? 'Expired' : 'Expires'} ${dateLabel(notice.expiresAt)}` : ''}
                    </p>
                </div>
                <h2 className="mt-4 text-2xl font-bold tracking-tight text-slate-950">{notice.title}</h2>
                <p className="mt-5 whitespace-pre-wrap text-base leading-7 text-slate-700">{notice.body}</p>
            </article>

            {showDetails && (
                <section className="mt-6 max-w-3xl rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <h2 className="font-semibold text-slate-900">Details</h2>
                    <dl className="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                        <div><dt className="text-xs font-medium text-slate-500">Audience</dt><dd className="mt-1 text-slate-900">{audienceLabel(notice.audience)}</dd></div>
                        <div><dt className="text-xs font-medium text-slate-500">Created by</dt><dd className="mt-1 text-slate-900">{notice.createdBy}</dd></div>
                        <div><dt className="text-xs font-medium text-slate-500">Created</dt><dd className="mt-1 text-slate-900">{dateTimeLabel(notice.createdAt)}</dd></div>
                        <div><dt className="text-xs font-medium text-slate-500">Expiry</dt><dd className="mt-1 text-slate-900">{dateLabel(notice.expiresAt) || 'No expiry'}</dd></div>
                    </dl>
                </section>
            )}
        </AppLayout>
    );
}