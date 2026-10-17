import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import PageAlerts from '../../Components/Exams/PageAlerts';
import { StageTracker, StatusBadge } from '../../Components/Admissions/AdmissionStage';
import AdmissionWorkflow from '../../Components/Admissions/AdmissionWorkflow';
import AdmissionDocuments from '../../Components/Admissions/AdmissionDocuments';
import AdmissionFollowUps from '../../Components/Admissions/AdmissionFollowUps';
import { humanize, sourceLabel, stageLabel } from '../../lib/admissions';

export default function AdmissionsShow({ admission, academicYears, today, followUpDefault, can }) {
    function deleteAdmission() {
        if (window.confirm(`Delete the record for ${admission.childName} (${admission.applicationNumber})? Its documents and follow-ups are deleted too.`)) {
            router.delete(`/admissions/${admission.id}`);
        }
    }

    return (
        <AppLayout title={admission.childName} description={`${admission.applicationNumber} · Received ${admission.createdAt}`}>
            <Head title={admission.childName} />
            <PageAlerts />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <Link href="/admissions" className="text-sm font-semibold text-teal-800 hover:text-teal-950">Back to admissions</Link>
                <div className="flex items-center gap-4 text-sm font-semibold">
                    {can.update && <Link href={`/admissions/${admission.id}/edit`} className="text-teal-800 hover:text-teal-950">Edit details</Link>}
                    {can.delete && <button type="button" onClick={deleteAdmission} className="text-rose-700 hover:text-rose-900">Delete</button>}
                </div>
            </div>

            <section className="mb-6 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <div className="mb-4 flex flex-wrap items-center gap-3">
                    <h2 className="font-semibold text-slate-900">Progress</h2>
                    <StatusBadge status={admission.status} />
                </div>
                <StageTracker status={admission.status} />
                {admission.reviewNotes && <p className="mt-4 rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-600">Review notes: {admission.reviewNotes}</p>}
                {admission.studentAdmissionNumber && (
                    <p className="mt-4 text-sm font-medium text-teal-800">
                        Enrolled as student{' '}
                        {admission.studentId
                            ? <Link href={`/students/${admission.studentId}`} className="underline">{admission.studentAdmissionNumber}</Link>
                            : admission.studentAdmissionNumber}
                    </p>
                )}
            </section>

            <div className="mb-6">
                <AdmissionWorkflow key={admission.status} admission={admission} academicYears={academicYears} today={today} can={can} />
            </div>

            <div className="mb-6 grid gap-6 xl:grid-cols-2">
                <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <h2 className="font-semibold text-slate-900">Child</h2>
                    <dl className="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                        <Detail label="Name" value={admission.childName} />
                        <Detail label="Date of birth" value={admission.dateOfBirth} />
                        <Detail label="Gender" value={humanize(admission.gender)} />
                        <Detail label="Applying for" value={admission.applyingFor} />
                        <Detail label="Enquiry source" value={sourceLabel(admission.source)} />
                        <div className="sm:col-span-2"><Detail label="Address" value={admission.childAddress} /></div>
                    </dl>
                </section>
                <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <h2 className="font-semibold text-slate-900">Parent / guardian</h2>
                    <dl className="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                        <Detail label="Name" value={admission.parentName} />
                        <Detail label="Phone" value={admission.parentPhone} />
                        <Detail label="Email" value={admission.parentEmail} />
                        <Detail label="Occupation" value={admission.parentOccupation} />
                        <div className="sm:col-span-2"><Detail label="Address" value={admission.parentAddress} /></div>
                    </dl>
                </section>
            </div>

            {(can.manageDocuments || can.manageFollowUps) && (
                <section className="mb-6 grid gap-6 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 lg:grid-cols-2">
                    {can.manageDocuments && <AdmissionDocuments admission={admission} />}
                    {can.manageFollowUps && <AdmissionFollowUps admission={admission} followUpDefault={followUpDefault} />}
                </section>
            )}

            <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <h2 className="font-semibold text-slate-900">Stage history</h2>
                <ol className="mt-4 space-y-4 border-l border-slate-200 pl-5">
                    {admission.history.map((entry) => (
                        <li key={entry.id} className="relative">
                            <span className="absolute -left-[1.58rem] top-1 size-2.5 rounded-full bg-teal-700 ring-4 ring-white" />
                            <p className="text-xs font-medium text-slate-500">{entry.at} · {entry.by}</p>
                            <p className="mt-1 text-sm font-semibold text-slate-900">
                                {entry.from ? `${stageLabel(entry.from)} → ${stageLabel(entry.to)}` : `Recorded as ${stageLabel(entry.to)}`}
                            </p>
                            {entry.notes && <p className="mt-1 whitespace-pre-wrap text-sm text-slate-600">{entry.notes}</p>}
                        </li>
                    ))}
                    {admission.history.length === 0 && (
                        <li className="text-sm text-slate-500">Stage changes are recorded here from now on. Earlier changes were not tracked.</li>
                    )}
                </ol>
            </section>
        </AppLayout>
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