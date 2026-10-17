import { Head, Link, useForm } from '@inertiajs/react';
import { router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import PageAlerts from '../../Components/PageAlerts';
import { Field, inputClass } from '../Auth/Login';
import { statusClass, typeLabel } from '../../lib/certificates';

/** Students and parents: request a certificate and open issued ones. */
export default function CertificatesPortal({ certificateRequests, certificateTypes, students, requiresStudentSelection }) {
    const form = useForm({
        certificate_type: certificateTypes[0] || '',
        reason: '',
        student_id: students[0]?.id || '',
    });

    function submitRequest(event) {
        event.preventDefault();
        form.post('/certificates', { preserveScroll: true, onSuccess: () => form.reset('reason') });
    }

    function cancelRequest(requestId) {
        router.post(`/certificates/${requestId}/cancel`, {}, { preserveScroll: true });
    }

    return (
        <AppLayout title="Certificates" description="Request and access student certificates.">
            <Head title="Certificates" />
            <PageAlerts />

            <div className="grid gap-6 xl:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)]">
                <section className="h-fit rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <h2 className="font-semibold text-slate-900">{requiresStudentSelection ? 'Request a certificate for a student' : 'Request a certificate'}</h2>
                    <p className="mt-1 text-sm leading-6 text-slate-500">Requests are reviewed by the school. Issued certificates can be printed or saved as PDF.</p>
                    <form onSubmit={submitRequest} className="mt-5 grid gap-4">
                        {requiresStudentSelection && (
                            <Field label="Linked student" error={form.errors.student_id}>
                                <select value={form.data.student_id} onChange={(event) => form.setData('student_id', event.target.value)} className={inputClass(form.errors.student_id)} required>
                                    <option value="">Select a student</option>
                                    {students.map((student) => <option key={student.id} value={student.id}>{student.name} · {student.admissionNumber}</option>)}
                                </select>
                            </Field>
                        )}
                        <Field label="Certificate type" error={form.errors.certificate_type}>
                            <select value={form.data.certificate_type} onChange={(event) => form.setData('certificate_type', event.target.value)} className={inputClass(form.errors.certificate_type)} required>
                                {certificateTypes.map((type) => <option key={type} value={type}>{typeLabel(type)}</option>)}
                            </select>
                        </Field>
                        <Field label="Reason for request" error={form.errors.reason}>
                            <textarea value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} className={inputClass(form.errors.reason)} rows={4} minLength={10} maxLength={2000} required />
                        </Field>
                        <button type="submit" disabled={form.processing} className="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-800 disabled:opacity-50">
                            {form.processing ? 'Submitting…' : 'Submit request'}
                        </button>
                    </form>
                </section>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    <div className="border-b border-slate-100 px-5 py-4 sm:px-6">
                        <h2 className="font-semibold text-slate-900">My certificate requests</h2>
                        <p className="mt-1 text-sm text-slate-500">{certificateRequests.total.toLocaleString()} request{certificateRequests.total === 1 ? '' : 's'}</p>
                    </div>
                    {certificateRequests.data.length === 0 ? (
                        <div className="px-6 py-12 text-center">
                            <p className="font-medium text-slate-800">No certificate requests yet</p>
                            <p className="mt-1 text-sm text-slate-500">Requests and issued certificate links will appear here.</p>
                        </div>
                    ) : (
                        <div className="divide-y divide-slate-100">
                            {certificateRequests.data.map((request) => (
                                <article key={request.id} className="p-5 sm:p-6">
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <h3 className="font-semibold text-slate-900">{typeLabel(request.certificateType)} certificate</h3>
                                            <p className="mt-1 text-sm text-slate-600">{request.studentName} · {request.admissionNumber}</p>
                                        </div>
                                        <span className={`rounded-full px-3 py-1 text-xs font-semibold capitalize ${statusClass(request.status)}`}>{request.status}</span>
                                    </div>
                                    <p className="mt-3 whitespace-pre-wrap text-sm leading-6 text-slate-700">{request.reason}</p>
                                    {request.reviewComments && <p className="mt-3 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600">{request.reviewComments}</p>}
                                    <div className="mt-4 flex flex-wrap items-center gap-2">
                                        {request.status === 'issued' && (
                                            <Link href={`/certificates/${request.id}`} className="rounded-lg bg-teal-700 px-3 py-2 text-sm font-semibold text-white hover:bg-teal-800">View certificate · {request.certificateNumber}</Link>
                                        )}
                                        {request.isOwn && request.status === 'pending' && (
                                            <button type="button" onClick={() => cancelRequest(request.id)} className="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancel request</button>
                                        )}
                                    </div>
                                </article>
                            ))}
                        </div>
                    )}
                    {certificateRequests.links.length > 3 && (
                        <nav aria-label="Certificate request pages" className="flex flex-wrap justify-between gap-2 border-t border-slate-100 px-5 py-4">
                            {certificateRequests.links.map((link) => (
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
            </div>
        </AppLayout>
    );
}