import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import { Field, inputClass } from '../Auth/Login';

function StudentSubmission({ assignment }) {
    const submission = assignment.mySubmission;
    const form = useForm({ content: submission?.content || '' });

    function submit(event) {
        event.preventDefault();
        form.post(`/homework/${assignment.id}/submissions`, { preserveScroll: true });
    }

    return (
        <div className="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4">
            {submission && (
                <div className="mb-4">
                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Submitted {new Date(submission.submittedAt).toLocaleString()}
                    </p>
                    {submission.score !== null && (
                        <div className="mt-3 rounded-lg bg-white p-3">
                            <p className="text-sm font-semibold text-slate-900">Score: {submission.score} / {assignment.maxPoints}</p>
                            {submission.gradedBy && <p className="mt-1 text-xs text-slate-500">Graded by {submission.gradedBy}</p>}
                            {submission.feedback && <p className="mt-2 whitespace-pre-wrap text-sm text-slate-700">{submission.feedback}</p>}
                        </div>
                    )}
                </div>
            )}
            {assignment.canSubmit ? (
                <form onSubmit={submit} className="grid gap-3">
                    <Field label={submission ? 'Update your submission before the due date' : 'Your submission'} error={form.errors.content}>
                        <textarea
                            value={form.data.content}
                            onChange={(event) => form.setData('content', event.target.value)}
                            className={inputClass(form.errors.content)}
                            rows={5}
                            maxLength={10000}
                            required
                        />
                    </Field>
                    <button type="submit" disabled={form.processing} className="w-fit rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800 disabled:opacity-50">
                        {form.processing ? 'Submitting…' : submission ? 'Update submission' : 'Submit homework'}
                    </button>
                </form>
            ) : (
                !submission && <p className="text-sm text-slate-500">This assignment is not accepting submissions.</p>
            )}
        </div>
    );
}

function TeacherSubmissions({ assignment }) {
    return (
        <div className="mt-5 border-t border-slate-100 pt-4">
            <h3 className="text-sm font-semibold text-slate-800">Submissions ({assignment.submissionCount})</h3>
            {assignment.submissions.length === 0 ? (
                <p className="mt-2 text-sm text-slate-500">No submissions yet.</p>
            ) : (
                <div className="mt-3 grid gap-3">
                    {assignment.submissions.map((submission) => (
                        <GradeSubmission key={submission.id} assignment={assignment} submission={submission} />
                    ))}
                </div>
            )}
        </div>
    );
}

function EditAssignment({ assignment }) {
    const [isEditing, setIsEditing] = useState(false);
    const form = useForm({
        title: assignment.title,
        instructions: assignment.instructions,
        assigned_date: assignment.assignedDate,
        due_date: assignment.dueDate,
        max_points: assignment.maxPoints,
    });

    function update(event) {
        event.preventDefault();
        form.put(`/homework/${assignment.id}`, {
            preserveScroll: true,
            onSuccess: () => setIsEditing(false),
        });
    }

    if (!isEditing) {
        return (
            <button type="button" onClick={() => setIsEditing(true)} className="mt-4 rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">
                Edit assignment
            </button>
        );
    }

    return (
        <form onSubmit={update} className="mt-4 grid gap-3 rounded-xl bg-slate-50 p-4 sm:grid-cols-2">
            <Field label="Title" error={form.errors.title}>
                <input value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} className={inputClass(form.errors.title)} maxLength={255} required />
            </Field>
            <Field label="Maximum points" error={form.errors.max_points}>
                <input type="number" min="1" max="10000" step="0.01" value={form.data.max_points} onChange={(event) => form.setData('max_points', event.target.value)} className={inputClass(form.errors.max_points)} required />
            </Field>
            <Field label="Assigned date" error={form.errors.assigned_date}>
                <input type="date" value={form.data.assigned_date} onChange={(event) => form.setData('assigned_date', event.target.value)} className={inputClass(form.errors.assigned_date)} required />
            </Field>
            <Field label="Due date" error={form.errors.due_date}>
                <input type="date" value={form.data.due_date} onChange={(event) => form.setData('due_date', event.target.value)} className={inputClass(form.errors.due_date)} required />
            </Field>
            <div className="sm:col-span-2">
                <Field label="Instructions" error={form.errors.instructions}>
                    <textarea value={form.data.instructions} onChange={(event) => form.setData('instructions', event.target.value)} className={inputClass(form.errors.instructions)} rows={4} maxLength={10000} required />
                </Field>
            </div>
            <div className="flex flex-wrap gap-2 sm:col-span-2">
                <button type="submit" disabled={form.processing} className="rounded-lg bg-teal-700 px-3 py-2 text-sm font-semibold text-white hover:bg-teal-800 disabled:opacity-50">
                    Save changes
                </button>
                <button type="button" onClick={() => setIsEditing(false)} className="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-white">
                    Cancel
                </button>
            </div>
        </form>
    );
}

function GradeSubmission({ assignment, submission }) {
    const form = useForm({
        score: submission.score ?? '',
        feedback: submission.feedback ?? '',
    });

    function grade(event) {
        event.preventDefault();
        form.patch(`/homework/${assignment.id}/submissions/${submission.id}/grade`, { preserveScroll: true });
    }

    return (
        <article className="rounded-xl border border-slate-200 p-4">
            <div className="flex flex-wrap justify-between gap-2">
                <div>
                    <p className="font-medium text-slate-900">{submission.studentName}</p>
                    <p className="text-xs text-slate-500">{submission.admissionNumber} · {new Date(submission.submittedAt).toLocaleString()}</p>
                </div>
                {submission.gradedBy && <span className="text-xs font-medium text-emerald-700">Graded by {submission.gradedBy}</span>}
            </div>
            <p className="mt-3 whitespace-pre-wrap text-sm leading-6 text-slate-700">{submission.content}</p>
            {assignment.canGrade && (
                <form onSubmit={grade} className="mt-4 grid gap-3 sm:grid-cols-[10rem_minmax(0,1fr)_auto] sm:items-end">
                    <Field label={`Score (max ${assignment.maxPoints})`} error={form.errors.score}>
                        <input
                            type="number"
                            min="0"
                            max={assignment.maxPoints}
                            step="0.01"
                            value={form.data.score}
                            onChange={(event) => form.setData('score', event.target.value)}
                            className={inputClass(form.errors.score)}
                            required
                        />
                    </Field>
                    <Field label="Feedback" error={form.errors.feedback}>
                        <input
                            type="text"
                            value={form.data.feedback}
                            onChange={(event) => form.setData('feedback', event.target.value)}
                            className={inputClass(form.errors.feedback)}
                            maxLength={5000}
                        />
                    </Field>
                    <button type="submit" disabled={form.processing} className="rounded-lg border border-teal-700 px-3 py-2 text-sm font-semibold text-teal-800 hover:bg-teal-50 disabled:opacity-50">
                        Save grade
                    </button>
                </form>
            )}
        </article>
    );
}

export default function HomeworkIndex({
    academicYear,
    assignments,
    subjectAssignments,
    canCreate,
    isStudent,
    isParent,
    children,
    selectedStudentId,
}) {
    const { status: flashStatus, errors = {} } = usePage().props;
    const pageErrors = Object.values(errors).flat();
    const firstAssignment = subjectAssignments[0];
    const form = useForm({
        section_id: firstAssignment?.sectionId || '',
        subject_id: firstAssignment?.subjectId || '',
        title: '',
        instructions: '',
        assigned_date: '',
        due_date: '',
        max_points: 100,
    });

    function updateAssignmentScope(value) {
        const option = subjectAssignments.find((item) => `${item.sectionId}:${item.subjectId}` === value);
        if (option) {
            form.setData({ ...form.data, section_id: option.sectionId, subject_id: option.subjectId });
        }
    }

    function createAssignment(event) {
        event.preventDefault();
        form.post('/homework', {
            preserveScroll: true,
            onSuccess: () => form.reset('title', 'instructions', 'assigned_date', 'due_date'),
        });
    }

    return (
        <AppLayout
            title="Homework"
            description={academicYear ? `Assignments for ${academicYear.name}.` : 'Assignments and student submissions.'}
        >
            <Head title="Homework" />
            {flashStatus && <div role="status" className="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{flashStatus}</div>}
            {pageErrors.length > 0 && (
                <div role="alert" className="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    {pageErrors.map((error) => <p key={error}>{error}</p>)}
                </div>
            )}

            {isParent && (
                <section className="mb-6 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <label className="grid max-w-sm gap-1 text-xs font-medium text-slate-600">
                        Child
                        <select
                            value={selectedStudentId || ''}
                            onChange={(event) => router.get('/homework', event.target.value ? { student_id: event.target.value } : {}, { preserveScroll: true, replace: true })}
                            className={inputClass(false)}
                        >
                            <option value="">All linked children</option>
                            {children.map((child) => <option key={child.id} value={child.id}>{child.name}</option>)}
                        </select>
                    </label>
                    <p className="mt-2 text-sm text-slate-500">Only assignments and submissions for your linked children are shown. Parent accounts cannot submit or grade work here.</p>
                </section>
            )}

            {canCreate && (
                <section className="mb-6 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <h2 className="font-semibold text-slate-900">Assign homework</h2>
                    <p className="mt-1 text-sm text-slate-500">Assignments are scoped to your current academic-year subject assignments.</p>
                    <form onSubmit={createAssignment} className="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        <Field label="Section and subject" error={form.errors.section_id || form.errors.subject_id}>
                            <select
                                value={`${form.data.section_id}:${form.data.subject_id}`}
                                onChange={(event) => updateAssignmentScope(event.target.value)}
                                className={inputClass(form.errors.section_id || form.errors.subject_id)}
                                required
                            >
                                {subjectAssignments.map((item) => (
                                    <option key={`${item.sectionId}:${item.subjectId}`} value={`${item.sectionId}:${item.subjectId}`}>{item.label}</option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Title" error={form.errors.title}>
                            <input value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} className={inputClass(form.errors.title)} maxLength={255} required />
                        </Field>
                        <Field label="Maximum points" error={form.errors.max_points}>
                            <input type="number" min="1" max="10000" step="0.01" value={form.data.max_points} onChange={(event) => form.setData('max_points', event.target.value)} className={inputClass(form.errors.max_points)} required />
                        </Field>
                        <Field label="Assigned date" error={form.errors.assigned_date}>
                            <input type="date" value={form.data.assigned_date} onChange={(event) => form.setData('assigned_date', event.target.value)} className={inputClass(form.errors.assigned_date)} required />
                        </Field>
                        <Field label="Due date" error={form.errors.due_date}>
                            <input type="date" value={form.data.due_date} onChange={(event) => form.setData('due_date', event.target.value)} className={inputClass(form.errors.due_date)} required />
                        </Field>
                        <div className="sm:col-span-2 xl:col-span-3">
                            <Field label="Instructions" error={form.errors.instructions}>
                                <textarea value={form.data.instructions} onChange={(event) => form.setData('instructions', event.target.value)} className={inputClass(form.errors.instructions)} rows={4} maxLength={10000} required />
                            </Field>
                        </div>
                        <div className="sm:col-span-2 xl:col-span-3">
                            <button type="submit" disabled={form.processing || subjectAssignments.length === 0} className="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-800 disabled:opacity-50">
                                {form.processing ? 'Saving…' : 'Publish assignment'}
                            </button>
                        </div>
                    </form>
                </section>
            )}

            <section className="grid gap-4">
                <div className="flex flex-wrap items-end justify-between gap-2">
                    <div>
                        <h2 className="font-semibold text-slate-900">{isStudent ? 'My assignments' : isParent ? 'Children’s assignments' : 'Academic-year assignments'}</h2>
                        <p className="mt-1 text-sm text-slate-500">
                            {academicYear ? academicYear.name : 'No active academic year'}
                            {` · ${assignments.total.toLocaleString()} assignment${assignments.total === 1 ? '' : 's'}`}
                        </p>
                    </div>
                </div>
                {assignments.data.length === 0 ? (
                    <div className="rounded-2xl border border-slate-200 bg-white px-6 py-12 text-center">
                        <p className="font-medium text-slate-800">No homework assignments found</p>
                        <p className="mt-1 text-sm text-slate-500">Assignments appear here for the active academic year and your assigned section.</p>
                    </div>
                ) : assignments.data.map((assignment) => (
                    <article key={assignment.id} className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 className="text-lg font-semibold text-slate-900">{assignment.title}</h3>
                                <p className="mt-1 text-sm text-slate-600">{assignment.subject} · {assignment.section}</p>
                            </div>
                            <div className="rounded-xl bg-amber-50 px-3 py-2 text-right text-xs text-amber-900">
                                <p className="font-semibold">Due {new Date(`${assignment.dueDate}T00:00:00`).toLocaleDateString()}</p>
                                <p className="mt-0.5">{assignment.maxPoints} points</p>
                            </div>
                        </div>
                        <p className="mt-4 whitespace-pre-wrap text-sm leading-6 text-slate-700">{assignment.instructions}</p>
                        <p className="mt-3 text-xs text-slate-500">Assigned {new Date(`${assignment.assignedDate}T00:00:00`).toLocaleDateString()} by {assignment.assignedBy}</p>
                        {isStudent
                            ? <StudentSubmission assignment={assignment} />
                            : isParent
                                ? (
                                    <div className="mt-5 grid gap-3 border-t border-slate-100 pt-4">
                                        <h4 className="text-sm font-semibold text-slate-800">Linked child submissions</h4>
                                        {assignment.childSubmissions.length === 0 ? (
                                            <p className="text-sm text-slate-500">No linked-child submissions yet.</p>
                                        ) : assignment.childSubmissions.map((submission) => (
                                            <div key={submission.id} className="rounded-xl bg-slate-50 p-4">
                                                <p className="font-medium text-slate-900">{submission.studentName}</p>
                                                <p className="mt-1 text-xs text-slate-500">Submitted {new Date(submission.submittedAt).toLocaleString()}</p>
                                                <p className="mt-3 whitespace-pre-wrap text-sm leading-6 text-slate-700">{submission.content}</p>
                                                {submission.score !== null && (
                                                    <div className="mt-3 rounded-lg bg-white p-3">
                                                        <p className="text-sm font-semibold text-slate-900">Score: {submission.score} / {assignment.maxPoints}</p>
                                                        {submission.gradedBy && <p className="mt-1 text-xs text-slate-500">Graded by {submission.gradedBy}</p>}
                                                        {submission.feedback && <p className="mt-2 whitespace-pre-wrap text-sm text-slate-700">{submission.feedback}</p>}
                                                    </div>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                )
                            : (
                                <>
                                    {assignment.canUpdate && <EditAssignment assignment={assignment} />}
                                    {assignment.canGrade && <TeacherSubmissions assignment={assignment} />}
                                </>
                            )}
                    </article>
                ))}
                {assignments.links.length > 3 && (
                    <nav aria-label="Homework pages" className="flex flex-wrap justify-center gap-2">
                        {assignments.links.map((link) => (
                            <button
                                key={link.label}
                                type="button"
                                disabled={!link.url || link.active}
                                onClick={() => link.url && router.get(link.url, {}, { preserveScroll: true })}
                                aria-current={link.active ? 'page' : undefined}
                                className={`rounded-lg px-3 py-2 text-sm ${link.active ? 'bg-teal-50 font-semibold text-teal-800' : 'text-slate-600'} disabled:opacity-40`}
                            >
                                {link.label.replace('&laquo;', '‹').replace('&raquo;', '›')}
                            </button>
                        ))}
                    </nav>
                )}
            </section>
        </AppLayout>
    );
}
