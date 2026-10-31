import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Plus } from 'lucide-react';
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
    const { status: flashStatus, errors = {} } = usePage().props;
    const pageErrors = Object.values(errors).flat();
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
                <section className="rounded-2xl border border-slate-200 bg-white p-6">
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

    return (
        <AppLayout title="Leave requests" description={isPortal ? 'Request leave for yourself or a linked student.' : 'Submit staff leave and review pending requests.'}>
            <Head title="Leave requests" />
            {flashStatus && <div role="status" className="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{flashStatus}</div>}
            {pageErrors.length > 0 && (
                <div role="alert" className="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    {pageErrors.map((error) => <p key={error}>{error}</p>)}
                </div>
            )}

            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div className="border-b border-slate-100 px-5 py-4 sm:px-6">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <h2 className="font-semibold text-slate-900">{title}</h2>
                        <p className="text-sm text-slate-500">
                            {leaveRequests.total.toLocaleString()} request{leaveRequests.total === 1 ? '' : 's'}
                        </p>
                    </div>
                    <div className="mt-3 inline-flex flex-wrap gap-1 rounded-xl bg-slate-100 p-1">
                        {canApply && (
                            <button
                                type="button"
                                onClick={() => setShowForm(true)}
                                className={`inline-flex items-center gap-1 whitespace-nowrap rounded-lg px-3 py-1.5 text-sm font-medium transition ${
                                    showForm ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'
                                }`}
                            >
                                <Plus size={14} />
                                New request
                            </button>
                        )}
                        {STATUS_TABS.map((tab) => {
                            const active = !showForm && activeStatus === tab.key;
                            return (
                                <button
                                    key={tab.key}
                                    type="button"
                                    onClick={() => changeStatus(tab.key)}
                                    className={`whitespace-nowrap rounded-lg px-3 py-1.5 text-sm font-medium transition ${
                                        active ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'
                                    }`}
                                >
                                    {tab.label}
                                </button>
                            );
                        })}
                    </div>
                </div>

                {showForm && canApply ? (
                    <div className="p-5 sm:p-6">
                        <form onSubmit={submitRequest} className="grid max-w-2xl gap-4">
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
                            <div className="flex flex-wrap items-center gap-2">
                                <button
                                    type="submit"
                                    disabled={form.processing}
                                    className="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-800 disabled:opacity-50"
                                >
                                    {form.processing ? 'Submitting…' : 'Submit request'}
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setShowForm(false)}
                                    className="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50"
                                >
                                    Cancel
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
                    <div className="grid gap-4 p-5 sm:grid-cols-2 sm:p-6">
                        {leaveRequests.data.map((request) => (
                            <article key={request.id} className="flex h-full flex-col rounded-2xl border border-slate-200 bg-white p-5">
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
                                    <button type="button" onClick={() => cancelRequest(request.id)} className="mt-auto self-start rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">
                                        Cancel request
                                    </button>
                                )}
                            </article>
                        ))}
                    </div>
                )}

                {!showForm && leaveRequests.links.length > 3 && (
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
        </AppLayout>
    );
}