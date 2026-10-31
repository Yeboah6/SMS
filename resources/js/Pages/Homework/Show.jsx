import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import PageAlerts from '../../Components/Exams/PageAlerts';
import Pill from '../../Components/Homework/Pill';
import StudentSubmission from '../../Components/Homework/StudentSubmission';
import GradeSubmission from '../../Components/Homework/GradeSubmission';
import { WINDOW, dateLabel } from '../../lib/homework';

export default function HomeworkShow({ role, assignment, stats, submissions, missing, mySubmission, childSubmissions, can }) {
    function deleteAssignment() {
        if (window.confirm(`Delete “${assignment.title}”? This cannot be undone.`)) {
            router.delete(`/homework/${assignment.id}`);
        }
    }

    return (
        <AppLayout title={assignment.title} description={`${assignment.subject} · ${assignment.section}`}>
            <Head title={assignment.title} />
            <PageAlerts />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <Link href="/homework" className="text-sm font-semibold text-teal-800 hover:text-teal-950">Back to homework</Link>
                <div className="flex items-center gap-4 text-sm font-semibold">
                    {can.update && <Link href={`/homework/${assignment.id}/edit`} className="text-teal-800 hover:text-teal-950">Edit assignment</Link>}
                    {can.delete && <button type="button" onClick={deleteAssignment} className="text-rose-700 hover:text-rose-900">Delete</button>}
                </div>
            </div>

            <section className="mb-6 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="flex flex-wrap items-center gap-2"><h2 className="text-lg font-semibold text-slate-900">{assignment.title}</h2><Pill map={WINDOW} value={assignment.window} /></div>
                    <div className="rounded-xl bg-amber-50 px-3 py-2 text-right text-xs text-amber-900"><p className="font-semibold">Due {dateLabel(assignment.dueDate)}</p><p className="mt-0.5">{assignment.maxPoints} points</p></div>
                </div>
                <p className="mt-4 whitespace-pre-wrap text-sm leading-6 text-slate-700">{assignment.instructions}</p>
                <p className="mt-4 text-xs text-slate-500">Assigned {dateLabel(assignment.assignedDate)} by {assignment.assignedBy}</p>
                {role === 'staff' && !can.delete && can.update && <p className="mt-3 text-xs text-slate-500">Students have submitted work, so this assignment can be edited but not deleted.</p>}
            </section>

            {role === 'student' && <StudentSubmission assignment={assignment} submission={mySubmission} canSubmit={can.submit} />}

            {role === 'parent' && (
                <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <h2 className="font-semibold text-slate-900">Linked child submissions</h2>
                    {childSubmissions.length === 0 ? <p className="mt-4 rounded-xl bg-slate-50 px-3 py-4 text-sm text-slate-500">No submissions yet.</p> : (
                        <div className="mt-4 grid gap-3">{childSubmissions.map((submission) => <GradeSubmission key={submission.id} assignment={assignment} submission={submission} canGrade={false} />)}</div>
                    )}
                </section>
            )}

            {role === 'staff' && (
                <>
                    <section className="mb-6 grid gap-3 sm:grid-cols-3">
                        {[['Submitted', `${stats.submitted} of ${stats.roster}`], ['Graded', stats.graded], ['Waiting to be graded', stats.submitted - stats.graded]].map(([label, value]) => <article key={label} className="rounded-2xl border border-slate-200 bg-white p-5"><p className="text-sm text-slate-500">{label}</p><p className="mt-2 text-2xl font-semibold tracking-tight text-slate-900">{value}</p></article>)}
                    </section>

                    <section className="mb-6 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                        <h2 className="font-semibold text-slate-900">Submissions</h2>
                        {submissions.length === 0 ? <p className="mt-4 rounded-xl bg-slate-50 px-3 py-4 text-sm text-slate-500">No submissions yet.</p> : (
                            <div className="mt-4 grid gap-3">{submissions.map((submission) => <GradeSubmission key={submission.id} assignment={assignment} submission={submission} canGrade={can.grade} />)}</div>
                        )}
                    </section>

                    {missing.length > 0 && (
                        <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                            <h2 className="font-semibold text-slate-900">Not submitted yet ({missing.length})</h2>
                            <ul className="mt-4 grid gap-2 text-sm sm:grid-cols-2 xl:grid-cols-3">
                                {missing.map((student) => <li key={student.id} className="rounded-xl border border-slate-100 px-3 py-2 text-slate-700">{student.name}<span className="block text-xs text-slate-500">{student.admissionNumber}</span></li>)}
                            </ul>
                        </section>
                    )}
                </>
            )}
        </AppLayout>
    );
}