import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Plus } from 'lucide-react';
import PageAlerts from '../../Components/Exams/PageAlerts';
import AppLayout from '../../Layouts/AppLayout';
import { Field, inputClass } from '../Auth/Login';

function formatDate(date) {
    return new Date(`${date}T00:00:00`).toLocaleDateString();
}

function statusClass(status) {
    if (status === 'approved') return 'bg-emerald-50 text-emerald-700';
    if (status === 'rejected') return 'bg-rose-50 text-rose-700';
    if (status === 'cancelled') return 'bg-slate-100 text-slate-600';
    return 'bg-amber-50 text-amber-800';
}

const STATUS_TABS = [
    { key: 'all', label: 'All' },
    { key: 'pending', label: 'Pending' },
    { key: 'approved', label: 'Approved' },
    { key: 'rejected', label: 'Rejected' },
];

function emptyState(status) {
    if (status === 'pending') return { title: 'No pending requests', description: 'New submissions awaiting review will appear here.' };
    if (status === 'approved') return { title: 'No approved requests', description: 'Approved leave will appear here.' };
    if (status === 'rejected') return { title: 'No rejected requests', description: 'Rejected requests will appear here for reference.' };
    return { title: 'No leave requests yet', description: 'Submitted requests and their review history will appear here.' };
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
    activeStatus = 'all',
}) {
    const [reviewComments, setReviewComments] = useState({});
    const [showForm, setShowForm] = useState(false);

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
            onSuccess: () => {
                form.reset('start_date', 'end_date', 'reason');
                setShowForm(false);
            },
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

    function changeStatus(status) {
        setShowForm(false);
        router.get('/leave-requests', status === 'all' ? {} : { status }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    // No permission at all — show a dedicated message instead of empty tabs.
    if (!canApply && !canReview) {
        return (
            <AppLayout title="Leave requests" description={isPortal ? 'Request leave for yourself or a linked student.' : 'Submit staff leave and review pending requests.'}>
                <Head title="Leave requests" />
                <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <h2 className="font-semibold text-slate-900">Leave access unavailable</h2>
                    <p className="mt-2 text-sm text-slate-600">
                        {hasActiveStaffProfile
                            ? 'Your account does not have permission to submit leave requests.'
                            : 'Your account is not linked to an active staff profile.'}
                    </p>
                </section>
            </AppLayout>
        );
    }

    const empty = emptyState(activeStatus);
    const title = canReview ? 'Staff and student leave requests' : 'My leave requests';
    const lastLinkIndex = leaveRequests.links.length - 1;

    return (
        <AppLayout title="Leave requests" description={isPortal ? 'Request leave for yourself or a linked student.' : 'Submit staff leave and review pending requests.'}>
            <Head title="Leave requests" />
            <PageAlerts />

            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div className="border-b border-slate-100 px-4 py-4 sm:px-6">
                    <div className="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                        <h2 className="font-semibold text-slate-900">{title}</h2>
                        <p className="text-sm text-slate-500">
                            {leaveRequests.total.toLocaleString()} request{leaveRequests.total === 1 ? '' : 's'}
                        </p>
                    </div>

                    <div className="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center">
                        {canApply && (
                            <button
                                type="button"
                                onClick={() => setShowForm(true)}
                                aria-pressed={showForm}
                                className={`inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-xl px-4 py-2.5 text-sm font-semibold text-white transition sm:py-2 ${
                                    showForm ? 'bg-teal-800' : 'bg-teal-700 hover:bg-teal-800'
                                }`}
                            >
                                <Plus size={14} />
                                New request
                            </button>
                        )}
                        <div className="grid grid-cols-4 gap-1 rounded-xl bg-slate-100 p-1 sm:inline-flex">
                            {STATUS_TABS.map((tab) => {
                                const active = !showForm && activeStatus === tab.key;
                                return (
                                    <button
                                        key={tab.key}
                                        type="button"
                                        onClick={() => changeStatus(tab.key)}
                                        className={`whitespace-nowrap rounded-lg px-2 py-2 text-sm font-medium transition sm:px-3 sm:py-1.5 ${
                                            active ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'
                                        }`}
                                    >
                                        {tab.label}
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                </div>

                {showForm && canApply ? (
                    <div className="p-4 sm:p-6">
                        <form onSubmit={submitRequest} className="grid max-w-2xl gap-4">
                            {requiresStudentSelection && (
                                <Field label="Linked student" error={form.errors.student_id}>
                                    <select value={form.data.student_id} onChange={(event) => form.setData('student_id', event.target.value)} className={`${inputClass(form.errors.student_id)} w-full`} required>
                                        <option value="">Select a student</option>
                                        {students.map((student) => <option key={student.id} value={student.id}>{student.name} · {student.admissionNumber}</option>)}
                                    </select>
                                </Field>
                            )}
                            <Field label="Leave type" error={form.errors.leave_type}>
                                <select
                                    value={form.data.leave_type}
                                    onChange={(event) => form.setData('leave_type', event.target.value)}
                                    className={`${inputClass(form.errors.leave_type)} w-full`}
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
                                        className={`${inputClass(form.errors.start_date)} w-full`}
                                        required
                                    />
                                </Field>
                                <Field label="Last day" error={form.errors.end_date}>
                                    <input
                                        type="date"
                                        value={form.data.end_date}
                                        onChange={(event) => form.setData('end_date', event.target.value)}
                                        className={`${inputClass(form.errors.end_date)} w-full`}
                                        required
                                    />
                                </Field>
                            </div>
                            <Field label="Reason" error={form.errors.reason}>
                                <textarea
                                    value={form.data.reason}
                                    onChange={(event) => form.setData('reason', event.target.value)}
                                    className={`${inputClass(form.errors.reason)} w-full`}
                                    rows={4}
                                    minLength={10}
                                    maxLength={2000}
                                    required
                                />
                            </Field>
                            <div className="flex flex-col-reverse gap-2 sm:flex-row sm:items-center">
                                <button
                                    type="button"
                                    onClick={() => setShowForm(false)}
                                    className="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={form.processing}
                                    className="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-800 disabled:opacity-50 sm:order-first"
                                >
                                    {form.processing ? 'Submitting…' : 'Submit request'}
                                </button>
                            </div>
                        </form>
                    </div>
                ) : leaveRequests.data.length === 0 ? (
                    <div className="px-6 py-12 text-center">
                        <p className="font-medium text-slate-800">{empty.title}</p>
                        <p className="mt-1 text-sm text-slate-500">{empty.description}</p>
                    </div>
                ) : (
                    <div className="grid gap-3 p-3 sm:grid-cols-2 sm:gap-4 sm:p-6">
                        {leaveRequests.data.map((request) => (
                            <article key={request.id} className="flex h-full min-w-0 flex-col rounded-2xl border border-slate-200 bg-white p-4 sm:p-5">
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <h3 className="font-semibold capitalize text-slate-900">{request.leaveType} leave</h3>
                                        <p className="mt-1 text-sm text-slate-600">
                                            {formatDate(request.startDate)} – {formatDate(request.endDate)}
                                        </p>
                                        <p className="mt-1 break-words text-xs text-slate-500">
                                            {request.subjectType === 'student' ? 'Student' : 'Staff'}: {request.staffName} · {request.staffNumber} · Submitted by {request.submittedBy}
                                        </p>
                                    </div>
                                    <span className={`shrink-0 whitespace-nowrap rounded-full px-3 py-1 text-xs font-semibold capitalize ${statusClass(request.status)}`}>
                                        {request.status}
                                    </span>
                                </div>
                                <p className="mt-4 whitespace-pre-wrap break-words text-sm leading-6 text-slate-700">{request.reason}</p>
                                {(request.reviewer || request.reviewComments) && (
                                    <div className="mt-4 rounded-xl bg-slate-50 px-3 py-3 text-sm text-slate-600 sm:px-4">
                                        {request.reviewer && <p className="font-medium text-slate-700">Reviewed by {request.reviewer}{request.reviewedAt ? ` · ${formatDate(request.reviewedAt.slice(0, 10))}` : ''}</p>}
                                        {request.reviewComments && <p className="mt-1 whitespace-pre-wrap break-words">{request.reviewComments}</p>}
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
                                                className={`${inputClass(false)} w-full`}
                                                rows={2}
                                                maxLength={2000}
                                            />
                                        </Field>
                                        <div className="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
                                            <button type="button" onClick={() => reviewRequest(request.id, 'approved')} className="rounded-lg bg-emerald-700 px-3 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800 sm:py-2">
                                                Approve
                                            </button>
                                            <button type="button" onClick={() => reviewRequest(request.id, 'rejected')} className="rounded-lg border border-rose-200 px-3 py-2.5 text-sm font-semibold text-rose-700 hover:bg-rose-50 sm:py-2">
                                                Reject
                                            </button>
                                        </div>
                                    </div>
                                )}
                                {request.isOwn && request.status === 'pending' && (
                                    <button type="button" onClick={() => cancelRequest(request.id)} className="mt-4 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50 sm:mt-auto sm:w-auto sm:self-start sm:py-2">
                                        Cancel request
                                    </button>
                                )}
                            </article>
                        ))}
                    </div>
                )}

                {!showForm && leaveRequests.links.length > 3 && (
                    <nav aria-label="Leave request pages" className="flex items-center justify-between gap-1 border-t border-slate-100 px-3 py-3 sm:px-5 sm:py-4">
                        {leaveRequests.links.map((link, index) => {
                            // On phones keep only previous, current page and next.
                            const alwaysVisible = index === 0 || index === lastLinkIndex || link.active;
                            return (
                                <button
                                    key={`${index}-${link.label}`}
                                    type="button"
                                    disabled={!link.url || link.active}
                                    onClick={() => link.url && router.get(link.url, {}, { preserveScroll: true })}
                                    className={`${alwaysVisible ? '' : 'hidden sm:block'} rounded-lg px-3 py-2 text-sm ${link.active ? 'bg-teal-50 font-semibold text-teal-800' : 'text-slate-600'} disabled:opacity-40`}
                                    aria-current={link.active ? 'page' : undefined}
                                >
                                    {link.label.replace('&laquo;', '‹').replace('&raquo;', '›')}
                                </button>
                            );
                        })}
                    </nav>
                )}
            </section>
        </AppLayout>
    );
}