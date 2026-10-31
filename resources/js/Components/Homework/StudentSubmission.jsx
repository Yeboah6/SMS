import { useForm } from '@inertiajs/react';
import { Field, inputClass } from '../../Pages/Auth/Login';
import { dateTimeLabel } from '../../lib/homework';

/** A student's own work on one assignment: submit, update until it is graded, and read the grade. */
export default function StudentSubmission({ assignment, submission, canSubmit }) {
    const form = useForm({ content: submission?.content || '' });

    function submit(event) {
        event.preventDefault();
        form.post(`/homework/${assignment.id}/submissions`, { preserveScroll: true });
    }

    return (
        <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
            <h2 className="font-semibold text-slate-900">Your submission</h2>
            {submission && (
                <div className="mt-3">
                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Submitted {dateTimeLabel(submission.submittedAt)}</p>
                    {submission.graded && (
                        <div className="mt-3 rounded-xl bg-slate-50 p-4">
                            <p className="text-sm font-semibold text-slate-900">Score: {submission.score} / {assignment.maxPoints}</p>
                            {submission.gradedBy && <p className="mt-1 text-xs text-slate-500">Graded by {submission.gradedBy}</p>}
                            {submission.feedback && <p className="mt-2 whitespace-pre-wrap text-sm text-slate-700">{submission.feedback}</p>}
                        </div>
                    )}
                </div>
            )}
            {canSubmit ? (
                <form onSubmit={submit} className="mt-4 grid gap-3">
                    <Field label={submission ? 'Update your submission before the due date' : 'Your submission'} error={form.errors.content}>
                        <textarea value={form.data.content} onChange={(event) => form.setData('content', event.target.value)} className={inputClass(form.errors.content)} rows={6} maxLength={10000} required />
                    </Field>
                    <button type="submit" disabled={form.processing} className="w-fit rounded-xl bg-teal-800 px-5 py-3 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-60">{form.processing ? 'Submitting…' : submission ? 'Update submission' : 'Submit homework'}</button>
                </form>
            ) : (
                !submission && <p className="mt-3 text-sm text-slate-500">This assignment is not accepting submissions.</p>
            )}
            {submission && !canSubmit && !submission.graded && <p className="mt-3 text-sm text-slate-500">Submissions are closed, so this can no longer be changed.</p>}
            {submission && <details className="mt-4"><summary className="cursor-pointer text-sm font-semibold text-teal-800">View what you submitted</summary><p className="mt-2 whitespace-pre-wrap text-sm leading-6 text-slate-700">{submission.content}</p></details>}
        </section>
    );
}