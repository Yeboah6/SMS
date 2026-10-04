import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { Field, inputClass } from '../Auth/Login';
import { Field, inputClass } from '../Auth/Login';

export default function StudentShow({ student, canEdit, canEnrol, canUploadDocuments, academicYears, classes, today }) {
    const { status } = usePage().props;
    const enrolmentForm = useForm({
        academic_year_id: '',
        class_id: '',
        section_id: '',
        enrolment_date: today,
    });
    const documentForm = useForm({ category: 'birth_certificate', document: null });
    const availableClasses = classes.filter((schoolClass) => String(schoolClass.academicYearId) === enrolmentForm.data.academic_year_id);
    const selectedClass = availableClasses.find((schoolClass) => String(schoolClass.id) === enrolmentForm.data.class_id);

    function enrol(event) {
        event.preventDefault();
        enrolmentForm.post(`/students/${student.id}/enrolments`, {
            preserveScroll: true,
            onSuccess: () => enrolmentForm.reset('academic_year_id', 'class_id', 'section_id'),
        });
    }

    function uploadDocument(event) {
        event.preventDefault();
        documentForm.post(`/students/${student.id}/documents`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => documentForm.reset('document'),
        });
    }

    return (
        <AppLayout title={student.name} description={`Student profile · ${student.admissionNumber}`}>
            <Head title={student.name} />
            {status && <div role="status" className="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{status}</div>}

            <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        {student.photoUrl && <img src={student.photoUrl} alt="" className="mb-3 size-16 rounded-full object-cover ring-2 ring-slate-100" />}
                        <p className="text-xs font-semibold uppercase tracking-wide text-teal-800">{student.admissionNumber}</p>
                        <h2 className="mt-1 text-2xl font-bold tracking-tight text-slate-950">{student.name}</h2>
                        <p className="mt-2 text-sm capitalize text-slate-500">{student.status} · admitted {student.admissionDate}</p>
                    </div>
                    {canEdit && <Link href={`/students/${student.id}/edit`} className="rounded-xl border border-slate-200 px-4 py-2.5 text-center text-sm font-semibold text-slate-700 hover:bg-slate-50">Edit profile</Link>}
                </div>
                <div className="mt-6 grid gap-4 border-t border-slate-100 pt-5 sm:grid-cols-2 xl:grid-cols-4">
                    <Detail label="Date of birth" value={student.dateOfBirth} />
                    <Detail label="Gender" value={student.gender.replaceAll('_', ' ')} />
                    <Detail label="Blood group" value={student.bloodGroup || 'Not recorded'} />
                    <Detail label="Religion" value={student.religion || 'Not recorded'} />
                    <div className="sm:col-span-2 xl:col-span-4"><Detail label="Address" value={student.address || 'Not recorded'} /></div>
                </div>
            </section>

            <div className="mt-6 grid gap-6 xl:grid-cols-2">
                <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <h2 className="font-semibold text-slate-900">Guardians</h2>
                    {student.guardians.length === 0 ? (
                        <p className="mt-4 text-sm text-slate-500">No guardian contacts are recorded.</p>
                    ) : (
                        <div className="mt-4 space-y-3">
                            {student.guardians.map((guardian) => (
                                <article key={guardian.id} className="rounded-xl border border-slate-100 p-4">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h3 className="font-medium text-slate-900">{guardian.name}</h3>
                                        {guardian.isPrimary && <span className="rounded-full bg-teal-50 px-2 py-0.5 text-[11px] font-semibold text-teal-800">Primary</span>}
                                        {guardian.livesWithStudent && <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600">Lives with student</span>}
                                    </div>
                                    <p className="mt-1 text-sm text-slate-600">{guardian.relationship} · {guardian.phone}</p>
                                    {guardian.email && <p className="mt-1 text-xs text-slate-500">{guardian.email}</p>}
                                    {guardian.occupation && <p className="mt-1 text-xs text-slate-500">{guardian.occupation}</p>}
                                </article>
                            ))}
                        </div>
                    )}
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <div className="flex items-center justify-between gap-3">
                        <h2 className="font-semibold text-slate-900">Academic enrolment history</h2>
                        <span className="text-xs text-slate-500">{student.enrolments.length} records</span>
                    </div>
                    {student.enrolments.length === 0 ? (
                        <p className="mt-4 text-sm text-slate-500">No academic-year enrolments are recorded.</p>
                    ) : (
                        <ol className="mt-4 space-y-3">
                            {student.enrolments.map((enrolment) => (
                                <li key={enrolment.id} className="relative rounded-xl border border-slate-100 p-4">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <p className="font-medium text-slate-900">{enrolment.academicYear}</p>
                                        <span className="rounded-full bg-slate-100 px-2 py-1 text-xs font-medium capitalize text-slate-600">{enrolment.outcome || enrolment.status}</span>
                                    </div>
                                    <p className="mt-1 text-sm text-slate-600">{enrolment.class} · Section {enrolment.section}</p>
                                    <p className="mt-1 text-xs text-slate-500">Enrolled {enrolment.date}</p>
                                </li>
                            ))}
                        </ol>
                    )}
                </section>
            </div>

            <section className="mt-6 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 className="font-semibold text-slate-900">Student documents</h2>
                        <p className="mt-1 text-sm text-slate-500">Files are stored privately and downloads require student-record access.</p>
                    </div>
                    <span className="text-xs text-slate-500">{student.documents.length} documents</span>
                </div>
                {student.documents.length > 0 && (
                    <ul className="mt-4 divide-y divide-slate-100 rounded-xl border border-slate-100">
                        {student.documents.map((document) => (
                            <li key={document.id} className="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-medium text-slate-800">{document.name}</p>
                                    <p className="mt-0.5 text-xs capitalize text-slate-500">{document.category.replaceAll('_', ' ')} · {(document.size / 1024).toFixed(0)} KB · {document.uploadedAt}</p>
                                </div>
                                <a href={document.downloadUrl} className="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Download</a>
                            </li>
                        ))}
                    </ul>
                )}
                {canUploadDocuments && (
                    <form onSubmit={uploadDocument} className="mt-4 grid gap-3 sm:grid-cols-[1fr_2fr_auto] sm:items-end">
                        <Field label="Document type" error={documentForm.errors.category}>
                            <select value={documentForm.data.category} onChange={(event) => documentForm.setData('category', event.target.value)} className={inputClass(documentForm.errors.category)}>
                                <option value="birth_certificate">Birth certificate</option>
                                <option value="transfer_certificate">Transfer certificate</option>
                                <option value="medical">Medical document</option>
                                <option value="other">Other</option>
                            </select>
                        </Field>
                        <Field label="File (PDF, JPEG, or PNG; up to 10 MB)" error={documentForm.errors.document}>
                            <input type="file" accept=".pdf,.jpg,.jpeg,.png" onChange={(event) => documentForm.setData('document', event.target.files?.[0] || null)} className={inputClass(documentForm.errors.document)} required />
                        </Field>
                        <button disabled={documentForm.processing} className="rounded-xl bg-teal-800 px-4 py-3 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-60">Upload</button>
                    </form>
                )}
            </section>

            {canEnrol && (
                <section className="mt-6 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <h2 className="font-semibold text-slate-900">Add academic enrolment</h2>
                    <p className="mt-1 text-sm text-slate-500">Each year is stored as a separate history record; existing enrolments are never overwritten.</p>
                    <form onSubmit={enrol} className="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <Field label="Academic year" error={enrolmentForm.errors.academic_year_id}>
                            <select value={enrolmentForm.data.academic_year_id} onChange={(event) => enrolmentForm.setData({ ...enrolmentForm.data, academic_year_id: event.target.value, class_id: '', section_id: '' })} className={inputClass(enrolmentForm.errors.academic_year_id)} required>
                                <option value="">Select year</option>
                                {academicYears.map((year) => <option key={year.id} value={year.id}>{year.name} · {year.status}</option>)}
                            </select>
                        </Field>
                        <Field label="Class" error={enrolmentForm.errors.class_id}>
                            <select value={enrolmentForm.data.class_id} onChange={(event) => enrolmentForm.setData({ ...enrolmentForm.data, class_id: event.target.value, section_id: '' })} className={inputClass(enrolmentForm.errors.class_id)} disabled={!enrolmentForm.data.academic_year_id} required>
                                <option value="">Select class</option>
                                {availableClasses.map((schoolClass) => <option key={schoolClass.id} value={schoolClass.id}>{schoolClass.name}</option>)}
                            </select>
                        </Field>
                        <Field label="Section" error={enrolmentForm.errors.section_id}>
                            <select value={enrolmentForm.data.section_id} onChange={(event) => enrolmentForm.setData('section_id', event.target.value)} className={inputClass(enrolmentForm.errors.section_id)} disabled={!selectedClass} required>
                                <option value="">Select section</option>
                                {selectedClass?.sections.map((section) => <option key={section.id} value={section.id}>{section.name}</option>)}
                            </select>
                        </Field>
                        <Field label="Enrolment date" error={enrolmentForm.errors.enrolment_date}>
                            <input type="date" value={enrolmentForm.data.enrolment_date} onChange={(event) => enrolmentForm.setData('enrolment_date', event.target.value)} className={inputClass(enrolmentForm.errors.enrolment_date)} required />
                        </Field>
                        <div className="flex items-end xl:col-span-4">
                            <button disabled={enrolmentForm.processing} className="rounded-xl bg-teal-800 px-4 py-3 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-60">Save enrolment</button>
                        </div>
                    </form>
                </section>
            )}
        </AppLayout>
    );
}

function Detail({ label, value }) {
    return (
        <div>
            <p className="text-xs font-medium text-slate-500">{label}</p>
            <p className="mt-1 text-sm capitalize text-slate-800">{value}</p>
        </div>
    );
}
