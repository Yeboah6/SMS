import { useForm } from '@inertiajs/react';
import { Field, inputClass } from '../../Pages/Auth/Login';
import { humanize } from '../../lib/admissions';

export default function AdmissionFollowUps({ admission, followUpDefault }) {
    const form = useForm({ follow_up_at: followUpDefault, method: 'phone', notes: '' });

    function schedule(event) {
        event.preventDefault();
        form.post(`/admissions/${admission.id}/follow-ups`, {
            preserveScroll: true,
            onSuccess: () => form.reset('notes'),
        });
    }

    return (
        <section>
            <h3 className="text-sm font-semibold text-slate-800">Follow-up history</h3>
            <ul className="mt-2 space-y-3">
                {admission.followUps.map((followUp) => <FollowUpItem key={followUp.id} followUp={followUp} />)}
                {admission.followUps.length === 0 && <li className="text-sm text-slate-500">No follow-ups scheduled.</li>}
            </ul>
            <form onSubmit={schedule} className="mt-3 grid gap-2 sm:grid-cols-2">
                <Field label="Follow-up date and time" error={form.errors.follow_up_at}>
                    <input type="datetime-local" value={form.data.follow_up_at} onChange={(event) => form.setData('follow_up_at', event.target.value)} className={inputClass(form.errors.follow_up_at)} />
                </Field>
                <Field label="Contact method" error={form.errors.method}>
                    <select value={form.data.method} onChange={(event) => form.setData('method', event.target.value)} className={inputClass(form.errors.method)}>
                        {['phone', 'email', 'in_person', 'other'].map((method) => <option key={method} value={method}>{humanize(method)}</option>)}
                    </select>
                </Field>
                <div className="sm:col-span-2">
                    <Field label="Follow-up notes" error={form.errors.notes}>
                        <textarea rows={2} value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} className={inputClass(form.errors.notes)} />
                    </Field>
                </div>
                <button disabled={form.processing} className="justify-self-start rounded-xl bg-teal-800 px-4 py-3 text-sm font-semibold text-white disabled:opacity-50">Schedule follow-up</button>
            </form>
        </section>
    );
}

function FollowUpItem({ followUp }) {
    const form = useForm({ outcome: '' });

    function complete(event) {
        event.preventDefault();
        form.patch(`/admission-follow-ups/${followUp.id}/complete`, { preserveScroll: true });
    }

    return (
        <li className="rounded-lg bg-slate-50 px-3 py-2">
            <p className="text-xs font-semibold text-slate-700">
                {new Date(followUp.followUpAt.replace(' ', 'T')).toLocaleString()} · {humanize(followUp.method)} · {followUp.status}
            </p>
            <p className="mt-1 whitespace-pre-wrap text-sm text-slate-600">{followUp.notes}</p>
            {followUp.outcome && <p className="mt-1 text-sm text-slate-600">Outcome: {followUp.outcome}</p>}
            {followUp.status === 'pending' && (
                <form onSubmit={complete} className="mt-2 flex flex-col gap-2 sm:flex-row sm:items-end">
                    <Field label="Completion outcome" error={form.errors.outcome}>
                        <input value={form.data.outcome} onChange={(event) => form.setData('outcome', event.target.value)} className={inputClass(form.errors.outcome)} required />
                    </Field>
                    <button disabled={form.processing} className="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 disabled:opacity-50">Mark complete</button>
                </form>
            )}
        </li>
    );
}