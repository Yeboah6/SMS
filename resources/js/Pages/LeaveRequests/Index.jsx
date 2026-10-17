import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import { Field, inputClass } from '../Auth/Login';

function formatDate(date) {
    return new Date(`${date}T00:00:00`).toLocaleDateString();
}

function statusClass(status) {
    if (status === 'approved') {
        return 'bg-emerald-50 text-emerald-700';
    }
    if (status === 'rejected') {
        return 'bg-rose-50 text-rose-700';
    }
    if (status === 'cancelled') {
        return 'bg-slate-100 text-slate-600';
    }

    return 'bg-amber-50 text-amber-800';
}

export default function LeaveRequestsIndex({
    leaveRequests,
    leaveTypes,
    canApply,
    canReview,
    hasActiveStaffProfile,
    isPortal,
    requiresStudentSelection,
    students,
}) {
    const { status: flashStatus, errors = {} } = usePage().props;
    const pageErrors = Object.values(errors).flat();
    const [reviewComments, setReviewComments] = useState({});
    const form = useForm({
        leave_type: leaveTypes[0] || '',
        start_date: '',
        end_date: '',
        reason: '',
        student_id: students[0]?.id || '',
    });

    function submitRequest(event) {
        event.preventDefault();
        form.post('/leave-requests', {
            preserveScroll: true,
            onSuccess: () => form.reset('start_date', 'end_date', 'reason'),
        });
    }

    function reviewRequest(requestId, outcome) {
        router.post(`/leave-requests/${requestId}/review`, {
            outcome,
            review_comments: reviewComments[requestId] || '',
        }, { preserveScroll: true });
    }

    function cancelRequest(requestId) {
        router.post(`/leave-requests/${requestId}/cancel`, {}, { preserveScroll: true });
    }

    return (
        <AppLayout title="Leave requests" description={isPortal ? 'Request leave for yourself or a linked student.' : 'Submit staff leave and review pending requests.'}>
            <Head title="Leave requests" />
            {flashStatus && <div role="status" className="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{flashStatus}</div>}
            {pageErrors.length > 0 && (
                <div role="alert" className="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    {pageErrors.map((error) => <p key={error}>{error}</p>)}
                </div>
            )}

            <div className="grid gap-6 xl:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)]">
                {canApply && (
                    <section className="h-fit rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                        <h2 className="font-semibold text-slate-900">{requiresStudentSelection ? 'Request leave for a student' : 'Request leave'}</h2>
                        <p className="mt-1 text-sm text-slate-500">Requests must be for a future date or today and cannot overlap another pending or approved request.</p>
                        <form onSubmit={submitRequest} className="mt-5 grid gap-4">
                            {requiresStudentSelection && (
                                <Field label="Linked student" error={form.errors.student_id}>
                                    <select value={form.data.student_id} onChange={(event) => form.setData('student_id', event.target.value)} className={inputClass(form.errors.student_id)} required>
                                        <option value="">Select a student</option>
                                        {students.map((student) => <option key={student.id} value={student.id}>{student.name} · {student.admissionNumber}</option>)}
                                    </select>
                                </Field>
                            )}
                            <Field label="Leave type" error={form.errors.leave_type}>
                                <select
                                    value={form.data.leave_type}
                                    onChange={(event) => form.setData('leave_type', event.target.value)}
                                    className={inputClass(form.errors.leave_type)}
                                    required
                                >
                                    {leaveTypes.map((type) => (
                                        <option key={type} value={type}>{type.charAt(0).toUpperCase() + type.slice(1)}</option>
                                    ))}
                                </select>
                            </Field>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field label="First day" error={form.errors.start_date}>
                                    <input
                                        type="date"
                                        value={form.data.start_date}
                                        onChange={(event) => form.setData('start_date', event.target.value)}
                                        className={inputClass(form.errors.start_date)}
                                        required
                                    />
                                </Field>
                                <Field label="Last day" error={form.errors.end_date}>
                                    <input
                                        type="date"
                                        value={form.data.end_date}
                                        onChange={(event) => form.setData('end_date', event.target.value)}
                                        className={inputClass(form.errors.end_date)}
                                        required
                                    />
                                </Field>
                            </div>
                            <Field label="Reason" error={form.errors.reason}>
                                <textarea
                                    value={form.data.reason}
                                    onChange={(event) => form.setData('reason', event.target.value)}
                                    className={inputClass(form.errors.reason)}
                                    rows={4}
                                    minLength={10}
                                    maxLength={2000}
                                    required
                                />
                            </Field>
                            <button
                                type="submit"
                                disabled={form.processing}
                                className="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-800 disabled:opacity-50"
                            >
                                {form.processing ? 'Submitting…' : 'Submit request'}
                            </button>
                        </form>
                    </section>
                )}

                {!canApply && !canReview && (
                    <section className="rounded-2xl border border-slate-200 bg-white p-6">
                        <h2 className="font-semibold text-slate-900">Leave access unavailable</h2>
                        <p className="mt-2 text-sm text-slate-600">
                            {hasActiveStaffProfile
                                ? 'Your account does not have permission to submit leave requests.'
                                : 'Your account is not linked to an active staff profile.'}
                        </p>
                    </section>
                )}

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    <div className="border-b border-slate-100 px-5 py-4 sm:px-6">
                        <h2 className="font-semibold text-slate-900">{canReview ? 'Staff and student leave requests' : 'My leave requests'}</h2>
                        <p className="mt-1 text-sm text-slate-500">{leaveRequests.total.toLocaleString()} request{leaveRequests.total === 1 ? '' : 's'}</p>
                    </div>

                    {leaveRequests.data.length === 0 ? (
                        <div className="px-6 py-12 text-center">
                            <p className="font-medium text-slate-800">No leave requests yet</p>
                            <p className="mt-1 text-sm text-slate-500">Submitted requests and their review history will appear here.</p>
                        </div>
                    ) : (
                        <div className="divide-y divide-slate-100">
                            {leaveRequests.data.map((request) => (
                                <article key={request.id} className="p-5 sm:p-6">
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <h3 className="font-semibold capitalize text-slate-900">{request.leaveType} leave</h3>
                                            <p className="mt-1 text-sm text-slate-600">
                                                {formatDate(request.startDate)} – {formatDate(request.endDate)}
                                            </p>
                                            <p className="mt-1 text-xs text-slate-500">
                                                {request.subjectType === 'student' ? 'Student' : 'Staff'}: {request.staffName} · {request.staffNumber} · Submitted by {request.submittedBy}
                                            </p>
                                        </div>
                                        <span className={`rounded-full px-3 py-1 text-xs font-semibold capitalize ${statusClass(request.status)}`}>
                                            {request.status}
                                        </span>
                                    </div>
                                    <p className="mt-4 whitespace-pre-wrap text-sm leading-6 text-slate-700">{request.reason}</p>
                                    {(request.reviewer || request.reviewComments) && (
                                        <div className="mt-4 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600">
                                            {request.reviewer && <p className="font-medium text-slate-700">Reviewed by {request.reviewer}{request.reviewedAt ? ` · ${formatDate(request.reviewedAt.slice(0, 10))}` : ''}</p>}
                                            {request.reviewComments && <p className="mt-1 whitespace-pre-wrap">{request.reviewComments}</p>}
                                        </div>
                                    )}
                                    {canReview && request.status === 'pending' && (
                                        <div className="mt-4 grid gap-3">
                                            <Field label="Review comments">
                                                <textarea
                                                    value={reviewComments[request.id] || ''}
                                                    onChange={(event) => setReviewComments((current) => ({
                                                        ...current,
                                                        [request.id]: event.target.value,
                                                    }))}
                                                    className={inputClass(false)}
                                                    rows={2}
                                                    maxLength={2000}
                                                />
                                            </Field>
                                            <div className="flex flex-wrap gap-2">
                                                <button type="button" onClick={() => reviewRequest(request.id, 'approved')} className="rounded-lg bg-emerald-700 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                                                    Approve
                                                </button>
                                                <button type="button" onClick={() => reviewRequest(request.id, 'rejected')} className="rounded-lg border border-rose-200 px-3 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50">
                                                    Reject
                                                </button>
                                            </div>
                                        </div>
                                    )}
                                    {request.isOwn && request.status === 'pending' && (
                                        <button type="button" onClick={() => cancelRequest(request.id)} className="mt-4 rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">
                                            Cancel request
                                        </button>
                                    )}
                                </article>
                            ))}
                        </div>
                    )}
                    {leaveRequests.links.length > 3 && (
                        <nav aria-label="Leave request pages" className="flex justify-between border-t border-slate-100 px-5 py-4">
                            {leaveRequests.links.map((link) => (
                                <button
                                    key={link.label}
                                    type="button"
                                    disabled={!link.url || link.active}
                                    onClick={() => link.url && router.get(link.url, {}, { preserveScroll: true })}
                                    className={`rounded-lg px-3 py-2 text-sm ${link.active ? 'bg-teal-50 font-semibold text-teal-800' : 'text-slate-600'} disabled:opacity-40`}
                                    aria-current={link.active ? 'page' : undefined}
                                >
                                    {link.label.replace('&laquo;', '‹').replace('&raquo;', '›')}
                                </button>
                            ))}
                        </nav>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}
