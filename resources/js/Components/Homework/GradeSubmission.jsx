import { useForm } from '@inertiajs/react';
import { Field, inputClass } from '../../Pages/Auth/Login';
import { dateTimeLabel } from '../../lib/homework';

/** One student's submission and, for teachers, the score and feedback form. */
export default function GradeSubmission({ assignment, submission, canGrade, showStudent = true }) {
    const form = useForm({ score: submission.score ?? '', feedback: submission.feedback ?? '' });

    function grade(event) {
        event.preventDefault();
        form.patch(`/homework/${assignment.id}/submissions/${submission.id}/grade`, { preserveScroll: true });
    }

    return (
        <article className="rounded-xl border border-slate-200 p-4">
            <div className="flex flex-wrap justify-between gap-2">
                <div>
                    {showStudent && <p className="font-medium text-slate-900">{submission.studentName}</p>}
                    <p className="text-xs text-slate-500">{showStudent && submission.admissionNumber ? `${submission.admissionNumber} · ` : ''}Submitted {dateTimeLabel(submission.submittedAt)}</p>
                </div>
                {submission.graded
                    ? <span className="text-xs font-medium text-emerald-700">Graded{submission.gradedBy ? ` by ${submission.gradedBy}` : ''}: {submission.score} / {assignment.maxPoints}</span>
                    : <span className="text-xs font-medium text-amber-700">Not graded yet</span>}
            </div>
            <p className="mt-3 whitespace-pre-wrap text-sm leading-6 text-slate-700">{submission.content}</p>
            {!canGrade && submission.graded && submission.feedback && <p className="mt-3 rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-700">{submission.feedback}</p>}
            {canGrade && (
                <form onSubmit={grade} className="mt-4 grid gap-3 sm:grid-cols-[10rem_minmax(0,1fr)_auto] sm:items-end">
                    <Field label={`Score (max ${assignment.maxPoints})`} error={form.errors.score}>
                        <input type="number" min="0" max={assignment.maxPoints} step="0.01" value={form.data.score} onChange={(event) => form.setData('score', event.target.value)} className={inputClass(form.errors.score)} required />
                    </Field>
                    <Field label="Feedback" error={form.errors.feedback}>
                        <input type="text" value={form.data.feedback} onChange={(event) => form.setData('feedback', event.target.value)} className={inputClass(form.errors.feedback)} maxLength={5000} />
                    </Field>
                    <button type="submit" disabled={form.processing} className="rounded-lg border border-teal-700 px-3 py-2.5 text-sm font-semibold text-teal-800 hover:bg-teal-50 disabled:opacity-50">{submission.graded ? 'Update grade' : 'Save grade'}</button>
                </form>
            )}
        </article>
    );
}