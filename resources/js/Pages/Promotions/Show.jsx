import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ShieldAlert, ShieldCheck } from 'lucide-react';
import PageAlerts from '../../Components/Exams/PageAlerts';
import AppLayout from '../../Layouts/AppLayout';

export default function PromotionsShow({ promotion, canReverse }) {
    const form = useForm({ reason: '' });

    function reverse(event) {
        event.preventDefault();

        if (window.confirm(`Reverse the recorded decision for ${promotion.student}? The target placement will be marked cancelled and the source placement restored.`)) {
            form.post(`/promotions/${promotion.id}/reverse`, { preserveScroll: true });
        }
    }

    return (
        <AppLayout title={`Promotion record #${promotion.id}`} description={`Academic decision for ${promotion.student}.`}>
            <Head title={`Promotion record #${promotion.id}`} />
            <PageAlerts />

            <div className="mb-6">
                <Link href="/promotions" className="inline-flex items-center gap-2 text-sm font-semibold text-slate-600 hover:text-teal-800">
                    <ArrowLeft size={16} aria-hidden="true" />
                    Promotion history
                </Link>
            </div>

            {promotion.reversedAt ? (
                <div className="mb-6 flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                    <ShieldAlert className="mt-0.5 shrink-0" size={18} aria-hidden="true" />
                    <div>
                        <p className="font-semibold">This promotion was reversed on {promotion.reversedAt} by {promotion.reversedBy || 'a former user'}.</p>
                        <p className="mt-1">{promotion.reversalReason}</p>
                    </div>
                </div>
            ) : null}

            <div className="grid gap-6 xl:grid-cols-2">
                <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Student</p>
                            <h2 className="mt-1 text-lg font-semibold text-slate-900">{promotion.student}</h2>
                            <p className="mt-1 text-sm text-slate-500">{promotion.admissionNumber}</p>
                        </div>
                        <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold capitalize text-slate-700">
                            {promotion.outcome}
                        </span>
                    </div>

                    <dl className="mt-6 grid gap-4 sm:grid-cols-2">
                        <Detail label="Source year" value={promotion.sourceYear} />
                        <Detail label="Source placement" value={`${promotion.sourceClass} · ${promotion.sourceSection}`} />
                        <Detail label="Target year" value={promotion.targetYear} />
                        <Detail
                            label="Target placement"
                            value={promotion.targetClass ? `${promotion.targetClass} · ${promotion.targetSection}` : 'Graduated — no target enrolment'}
                        />
                        <Detail label="Current source enrolment state" value={promotion.sourceStatus} capitalize />
                        <Detail label="Current target enrolment state" value={promotion.targetStatus || 'Not applicable'} capitalize />
                        <Detail label="Recorded by" value={promotion.processedBy} />
                        <Detail label="Recorded at" value={promotion.processedAt} />
                        <Detail label="Decision reason" value={promotion.reason || 'No reason recorded'} wide />
                    </dl>
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <div className="flex items-start gap-3">
                        <ShieldCheck className="mt-0.5 shrink-0 text-teal-700" size={20} aria-hidden="true" />
                        <div>
                            <h2 className="font-semibold text-slate-900">Audited correction</h2>
                            <p className="mt-1 text-sm leading-6 text-slate-600">
                                Promotion decisions are kept as history. They cannot be edited or deleted; reversing a decision preserves the original record and requires a reason.
                            </p>
                        </div>
                    </div>

                    {promotion.reversedAt ? (
                        <p className="mt-5 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600">
                            This decision is already reversed. The original record remains available for audit.
                        </p>
                    ) : promotion.reversalBlockReason ? (
                        <p role="status" className="mt-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                            {promotion.reversalBlockReason}
                        </p>
                    ) : canReverse ? (
                        <form onSubmit={reverse} className="mt-5 border-t border-slate-100 pt-5">
                            <label className="block text-sm font-medium text-slate-700" htmlFor="reversal-reason">
                                Reason for reversal
                            </label>
                            <textarea
                                id="reversal-reason"
                                rows={4}
                                minLength={10}
                                maxLength={2000}
                                required
                                value={form.data.reason}
                                onChange={(event) => form.setData('reason', event.target.value)}
                                className={`mt-2 w-full rounded-xl border px-3 py-2 text-sm ${form.errors.reason ? 'border-rose-400' : 'border-slate-200'}`}
                                aria-invalid={Boolean(form.errors.reason)}
                                aria-describedby={form.errors.reason ? 'reversal-reason-error' : undefined}
                            />
                            {form.errors.reason && (
                                <p id="reversal-reason-error" role="alert" className="mt-1 text-sm text-rose-700">
                                    {form.errors.reason}
                                </p>
                            )}
                            {form.errors.promotion && (
                                <p role="alert" className="mt-2 text-sm text-rose-700">{form.errors.promotion}</p>
                            )}
                            <button
                                disabled={form.processing}
                                className="mt-4 rounded-xl border border-rose-200 px-4 py-2.5 text-sm font-semibold text-rose-700 hover:bg-rose-50 disabled:opacity-60"
                            >
                                {form.processing ? 'Reversing…' : 'Reverse decision'}
                            </button>
                        </form>
                    ) : (
                        <p className="mt-5 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600">
                            You do not have permission to reverse this decision.
                        </p>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}

function Detail({ label, value, capitalize = false, wide = false }) {
    return (
        <div className={wide ? 'sm:col-span-2' : ''}>
            <dt className="text-xs font-medium uppercase tracking-wide text-slate-500">{label}</dt>
            <dd className={`mt-1 whitespace-pre-wrap text-sm text-slate-900 ${capitalize ? 'capitalize' : ''}`}>{value || '—'}</dd>
        </div>
    );
}
