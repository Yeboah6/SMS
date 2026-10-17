import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import PageAlerts from '../../Components/Exams/PageAlerts';
import CertificateDocument from '../../Components/Certificates/CertificateDocument';
import { Field, inputClass } from '../Auth/Login';
import { statusClass, typeLabel } from '../../lib/certificates';

export default function CertificatesShow({ mode, certificate, request, can }) {
    if (mode === 'portal') {
        return (
            <AppLayout title="Issued certificate" description={`${certificate.number} · ${certificate.studentName}`}>
                <Head title={`${certificate.number} · Certificate`} />
                <div className="mb-5 flex flex-wrap items-center justify-between gap-3 print:hidden">
                    <Link href="/certificates" className="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Back to certificates</Link>
                    <PrintButton />
                </div>
                <CertificateDocument certificate={certificate} />
            </AppLayout>
        );
    }

    return <ManagerView certificate={certificate} request={request} can={can} />;
}

function ManagerView({ certificate, request, can }) {
    const [comments, setComments] = useState('');
    const revokeForm = useForm({ reason: '' });

    function review(outcome) {
        router.post(`/certificates/${request.id}/review`, { outcome, review_comments: comments }, { preserveScroll: true });
    }

    function revoke(event) {
        event.preventDefault();
        if (window.confirm(`Revoke certificate ${request.certificateNumber}? It will be marked VOID and hidden from the student and parents.`)) {
            revokeForm.post(`/certificates/${request.id}/revoke`, { preserveScroll: true });
        }
    }

    function deleteRequest() {
        if (window.confirm('Delete this certificate request?')) {
            router.delete(`/certificates/${request.id}`);
        }
    }

    return (
        <AppLayout title={`${typeLabel(request.certificateType)} certificate`} description={`${request.studentName} · ${request.admissionNumber}`}>
            <Head title={`${typeLabel(request.certificateType)} certificate`} />

            <div className="print:hidden">
                <PageAlerts />

                <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                    <Link href="/certificates" className="text-sm font-semibold text-teal-800 hover:text-teal-950">Back to certificates</Link>
                    <div className="flex flex-wrap items-center gap-4 text-sm font-semibold">
                        {certificate && request.status === 'issued' && <PrintButton />}
                        {can.update && <Link href={`/certificates/${request.id}/edit`} className="text-teal-800 hover:text-teal-950">Edit request</Link>}
                        {can.delete && <button type="button" onClick={deleteRequest} className="text-rose-700 hover:text-rose-900">Delete</button>}
                    </div>
                </div>

                <section className="mb-6 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <h2 className="font-semibold text-slate-900">Request</h2>
                        <span className={`rounded-full px-3 py-1 text-xs font-semibold capitalize ${statusClass(request.status)}`}>{request.status}</span>
                    </div>
                    <dl className="mt-4 grid gap-4 text-sm sm:grid-cols-2 xl:grid-cols-3">
                        <Detail label="Student" value={<Link href={`/students/${request.studentId}`} className="text-teal-800 hover:text-teal-950">{request.studentName}</Link>} />
                        <Detail label="Admission number" value={request.admissionNumber} />
                        <Detail label="Type" value={typeLabel(request.certificateType)} />
                        <Detail label="Requested by" value={request.requestedBy} />
                        <Detail label="Requested" value={request.requestedAt} />
                        <Detail label="Certificate number" value={request.certificateNumber} />
                        <Detail label="Reviewed by" value={request.reviewer} />
                        <Detail label="Issued" value={request.issuedAt} />
                        <div className="sm:col-span-2 xl:col-span-3"><Detail label="Reason" value={<span className="whitespace-pre-wrap">{request.reason}</span>} /></div>
                        {request.reviewComments && <div className="sm:col-span-2 xl:col-span-3"><Detail label="Review comments" value={<span className="whitespace-pre-wrap">{request.reviewComments}</span>} /></div>}
                    </dl>
                </section>

                {request.status === 'revoked' && (
                    <section role="status" className="mb-6 rounded-2xl border border-rose-200 bg-rose-50 p-5 sm:p-6">
                        <h2 className="font-semibold text-rose-900">Revoked</h2>
                        <p className="mt-1 text-sm text-rose-800">{request.revokedAt} by {request.revokedBy}</p>
                        <p className="mt-2 whitespace-pre-wrap text-sm text-rose-900">{request.revocationReason}</p>
                    </section>
                )}

                {can.review && (
                    <section className="mb-6 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                        <h2 className="font-semibold text-slate-900">Review this request</h2>
                        <div className="mt-4 max-w-2xl">
                            <Field label="Review comments">
                                <textarea rows={2} value={comments} onChange={(event) => setComments(event.target.value)} className={inputClass(false)} maxLength={2000} />
                            </Field>
                        </div>
                        <div className="mt-4 flex flex-wrap gap-2">
                            <button type="button" onClick={() => review('issued')} className="rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800">Approve and issue</button>
                            <button type="button" onClick={() => review('rejected')} className="rounded-lg border border-rose-200 px-4 py-2.5 text-sm font-semibold text-rose-700 hover:bg-rose-50">Reject</button>
                        </div>
                    </section>
                )}

                {can.revoke && (
                    <section className="mb-6 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                        <h2 className="font-semibold text-slate-900">Revoke certificate</h2>
                        <p className="mt-1 text-sm text-slate-500">Use this if the certificate was issued in error. Its number stays used.</p>
                        <form onSubmit={revoke} className="mt-4 max-w-2xl">
                            <Field label="Reason for revoking" error={revokeForm.errors.reason}>
                                <textarea rows={2} value={revokeForm.data.reason} onChange={(event) => revokeForm.setData('reason', event.target.value)} className={inputClass(revokeForm.errors.reason)} minLength={10} maxLength={2000} required />
                            </Field>
                            <button disabled={revokeForm.processing} className="mt-3 rounded-lg bg-rose-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-rose-800 disabled:opacity-60">Revoke certificate</button>
                        </form>
                    </section>
                )}

                {certificate && <h2 className="mb-3 font-semibold text-slate-900">Certificate preview</h2>}
            </div>

            {certificate && <CertificateDocument certificate={certificate} />}
        </AppLayout>
    );
}

function PrintButton() {
    return (
        <button type="button" onClick={() => window.print()} className="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-800">Print / Save as PDF</button>
    );
}

function Detail({ label, value }) {
    return (
        <div>
            <dt className="text-xs font-medium text-slate-500">{label}</dt>
            <dd className="mt-1 text-slate-900">{value || '—'}</dd>
        </div>
    );
}