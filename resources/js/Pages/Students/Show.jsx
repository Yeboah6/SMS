import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import PageAlerts from '../../Components/Exams/PageAlerts';
import AppLayout from '../../Layouts/AppLayout';
import { Field, inputClass } from '../Auth/Login';

export default function StudentShow({ student, canEdit, canDelete, canEnrol, canTransfer, transferEnrolmentId, transfers, canUploadDocuments, canViewNotes, canCreateNotes, canViewAuditHistory, auditEntries, academicYears, classes, today, canManageGuardianAccounts, eligibleParentAccounts, canViewAttendance, attendanceRecords, canViewResults, examMarks, canViewFees, invoices, canViewHomework, homeworkSubmissions, history }) {
    // const { status } = usePage().props;
    const [activeTab, setActiveTab] = useState('overview');
    const enrolmentForm = useForm({
        academic_year_id: '',
        class_id: '',
        section_id: '',
        enrolment_date: today,
    });
    const documentForm = useForm({ category: 'birth_certificate', document: null });
    const transferForm = useForm({
        enrolment_id: transferEnrolmentId || '',
        transfer_date: today,
        destination_school: '',
        reason: '',
    });
    const availableClasses = classes.filter((schoolClass) => String(schoolClass.academicYearId) === enrolmentForm.data.academic_year_id);
    const selectedClass = availableClasses.find((schoolClass) => String(schoolClass.id) === enrolmentForm.data.class_id);
    const tabs = [
        ['overview', 'Overview'],
        ...(canViewAttendance ? [['attendance', 'Attendance']] : []),
        ...(canViewResults ? [['results', 'Results']] : []),
        ...(canViewFees ? [['fees', 'Fees']] : []),
        ...(canViewHomework ? [['homework', 'Homework']] : []),
        ['history', 'History'],
    ];

    function enrol(event) {
        event.preventDefault();
        enrolmentForm.post(`/students/${student.id}/enrolments`, {
            preserveScroll: true,
            onSuccess: () => enrolmentForm.reset('academic_year_id', 'class_id', 'section_id'),
        });
    }

    function deleteStudent() {
        if (window.confirm(`Delete ${student.name}? Students with enrolment, attendance, results or fee history cannot be deleted; mark them inactive instead.`)) {
            router.delete(`/students/${student.id}`);
        }
    }

    function uploadDocument(event) {
        event.preventDefault();
        documentForm.post(`/students/${student.id}/documents`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => documentForm.reset('document'),
        });
    }

    function recordTransfer(event) {
        event.preventDefault();
        transferForm.post(`/students/${student.id}/transfers`, {
            preserveScroll: true,
            onSuccess: () => transferForm.reset('destination_school', 'reason'),
        });
    }

    const baseUrl = `/students`;

    return (
        <AppLayout title={student.name} description={`Student profile · ${student.admissionNumber}`}>
            <Head title={student.name} />
            <PageAlerts />

            
            <div className="mb-5">
                <Link href={baseUrl} className="text-sm font-semibold text-teal-800 hover:text-teal-950">← Back to students </Link>
            </div>

            <nav aria-label="Student profile sections" className="mb-5 flex gap-2 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-2">
                {tabs.map(([key, label]) => (
                    <button key={key} type="button" aria-pressed={activeTab === key} onClick={() => setActiveTab(key)} className={`shrink-0 rounded-xl px-4 py-2.5 text-sm font-semibold ${activeTab === key ? 'bg-teal-800 text-white' : 'text-slate-600 hover:bg-slate-100'}`}>
                        {label}
                    </button>
                ))}
            </nav>

            {activeTab === 'overview' && <>
            <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        {student.photoUrl && <img src={student.photoUrl} alt="" className="mb-3 size-16 rounded-full object-cover ring-2 ring-slate-100" />}
                        <p className="text-xs font-semibold uppercase tracking-wide text-teal-800">{student.admissionNumber}</p>
                        <h2 className="mt-1 text-2xl font-bold tracking-tight text-slate-950">{student.name}</h2>
                        <p className="mt-2 text-sm capitalize text-slate-500">{student.status} · admitted {student.admissionDate}</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <a href={`/students/${student.id}/id-card`} className="rounded-xl border border-teal-200 px-4 py-2.5 text-center text-sm font-semibold text-teal-800 hover:bg-teal-50">Download ID card</a>
                        {canEdit && <Link href={`/students/${student.id}/edit`} className="rounded-xl border border-slate-200 px-4 py-2.5 text-center text-sm font-semibold text-slate-700 hover:bg-slate-50">Edit profile</Link>}
                        {canDelete && <button type="button" onClick={deleteStudent} className="rounded-xl border border-rose-200 px-4 py-2.5 text-center text-sm font-semibold text-rose-700 hover:bg-rose-50">Delete student</button>}
                    </div>
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
                                    {canManageGuardianAccounts && (
                                        <GuardianAccountControls guardian={guardian} studentId={student.id} parentAccounts={eligibleParentAccounts} />
                                    )}
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

            {(canTransfer || transfers.length > 0) && (
                <section className="mt-6 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <h2 className="font-semibold text-slate-900">Transfer history</h2>
                    {transfers.length > 0 ? (
                        <ol className="mt-4 space-y-3">
                            {transfers.map((transfer) => (
                                <li key={`${transfer.academicYear}-${transfer.date}`} className="rounded-xl border border-slate-100 p-4">
                                    <p className="font-medium text-slate-900">{transfer.destinationSchool}</p>
                                    <p className="mt-1 text-sm text-slate-600">{transfer.academicYear} · {transfer.date}</p>
                                    <p className="mt-2 whitespace-pre-wrap text-sm text-slate-700">{transfer.reason}</p>
                                    <p className="mt-2 text-xs text-slate-500">Recorded by {transfer.processedBy}</p>
                                </li>
                            ))}
                        </ol>
                    ) : (
                        <p className="mt-3 text-sm text-slate-500">No transfers have been recorded.</p>
                    )}
                    {canTransfer && (
                        <form onSubmit={recordTransfer} className="mt-5 grid gap-4 border-t border-slate-100 pt-5 sm:grid-cols-2">
                            <Field label="Transfer date" error={transferForm.errors.transfer_date}>
                                <input type="date" value={transferForm.data.transfer_date} onChange={(event) => transferForm.setData('transfer_date', event.target.value)} className={inputClass(transferForm.errors.transfer_date)} required />
                            </Field>
                            <Field label="Destination school" error={transferForm.errors.destination_school}>
                                <input value={transferForm.data.destination_school} onChange={(event) => transferForm.setData('destination_school', event.target.value)} className={inputClass(transferForm.errors.destination_school)} maxLength={255} required />
                            </Field>
                            <div className="sm:col-span-2">
                                <Field label="Reason" error={transferForm.errors.reason}>
                                    <textarea value={transferForm.data.reason} onChange={(event) => transferForm.setData('reason', event.target.value)} className={inputClass(transferForm.errors.reason)} rows={3} minLength={10} maxLength={2000} required />
                                </Field>
                            </div>
                            {transferForm.errors.enrolment_id && <p role="alert" className="text-sm text-rose-700 sm:col-span-2">{transferForm.errors.enrolment_id}</p>}
                            <div className="sm:col-span-2">
                                <button disabled={transferForm.processing} className="rounded-xl bg-rose-700 px-4 py-3 text-sm font-semibold text-white hover:bg-rose-800 disabled:opacity-60">
                                    {transferForm.processing ? 'Recording transfer…' : 'Record transfer out'}
                                </button>
                            </div>
                        </form>
                    )}
                </section>
            )}

            {canViewNotes && (
                <section className="mt-6 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 className="font-semibold text-slate-900">Student notes</h2>
                            <p className="mt-1 text-sm text-slate-500">A dated timeline of academic, welfare, and communication notes.</p>
                        </div>
                        <div className="flex items-center gap-2">
                            <span className="text-xs text-slate-500">{student.notes.length} recent notes</span>
                            <Link href={`/students/${student.id}/notes`} className="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Manage notes</Link>
                            {canCreateNotes && <Link href={`/students/${student.id}/notes/create`} className="rounded-lg bg-teal-800 px-3 py-2 text-xs font-semibold text-white hover:bg-teal-900">Add note</Link>}
                        </div>
                    </div>
                    {student.notes.length === 0 ? (
                        <p className="mt-4 rounded-xl bg-slate-50 px-4 py-5 text-sm text-slate-500">No notes have been recorded.</p>
                    ) : (
                        <ol className="mt-4 space-y-3">
                            {student.notes.map((note) => (
                                <li key={note.id} className="rounded-xl border border-slate-100 p-4">
                                    <div className="flex flex-wrap items-start justify-between gap-2">
                                        <div>
                                            <Link href={note.showUrl} className="font-medium text-slate-900 hover:text-teal-800">{note.title}</Link>
                                            <p className="mt-1 text-xs capitalize text-slate-500">{note.type.replaceAll('_', ' ')} · {note.date} · by {note.author}</p>
                                        </div>
                                        <span className="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-medium capitalize text-slate-600">{note.visibility.replaceAll('_', ' ')}</span>
                                    </div>
                                    <p className="mt-3 whitespace-pre-wrap text-sm leading-6 text-slate-700">{note.content}</p>
                                    {note.attachmentUrl && (
                                        <a href={note.attachmentUrl} className="mt-3 inline-flex rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                            Download {note.attachmentName} · {(note.attachmentSize / 1024).toFixed(0)} KB
                                        </a>
                                    )}
                                </li>
                            ))}
                        </ol>
                    )}
                </section>
            )}

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
            </>}

            {activeTab === 'attendance' && canViewAttendance && (
                <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <h2 className="font-semibold text-slate-900">Attendance history</h2>
                    <div className="mt-4 overflow-x-auto">
                        <table className="w-full min-w-[620px] text-left text-sm">
                            <thead className="border-b border-slate-100 text-xs uppercase text-slate-500"><tr><th className="py-3 pr-4">Date</th><th className="py-3 pr-4">Year</th><th className="py-3 pr-4">Class</th><th className="py-3 pr-4">Status</th><th className="py-3">Remarks</th></tr></thead>
                            <tbody>{attendanceRecords.data.map((record) => <tr key={record.id} className="border-b border-slate-50"><td className="py-3 pr-4">{record.date}</td><td className="py-3 pr-4">{record.academicYear}</td><td className="py-3 pr-4">{record.class} · {record.section}</td><td className="py-3 pr-4 capitalize">{record.status}</td><td className="py-3">{record.remarks || '—'}</td></tr>)}</tbody>
                        </table>
                    </div>
                    {attendanceRecords.data.length === 0 && <EmptyMessage>No attendance records found.</EmptyMessage>}
                    <Pagination links={attendanceRecords.links} />
                </section>
            )}

            {activeTab === 'results' && canViewResults && (
                <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <h2 className="font-semibold text-slate-900">Published examination results</h2>
                    <div className="mt-4 overflow-x-auto">
                        <table className="w-full min-w-[620px] text-left text-sm">
                            <thead className="border-b border-slate-100 text-xs uppercase text-slate-500"><tr><th className="py-3 pr-4">Exam</th><th className="py-3 pr-4">Year / term</th><th className="py-3 pr-4">Subject</th><th className="py-3 pr-4">Date</th><th className="py-3 pr-4">Marks</th><th className="py-3">Status</th></tr></thead>
                            <tbody>{examMarks.data.map((mark) => <tr key={mark.id} className="border-b border-slate-50"><td className="py-3 pr-4">{mark.exam}</td><td className="py-3 pr-4">{mark.academicYear}{mark.term ? ` · ${mark.term}` : ''}</td><td className="py-3 pr-4">{mark.subject}</td><td className="py-3 pr-4">{mark.date}</td><td className="py-3 pr-4">{mark.marks ?? '—'} / {mark.maxMarks}</td><td className="py-3 capitalize">{mark.status}</td></tr>)}</tbody>
                        </table>
                    </div>
                    {examMarks.data.length === 0 && <EmptyMessage>No published results found.</EmptyMessage>}
                    <Pagination links={examMarks.links} />
                </section>
            )}

            {activeTab === 'fees' && canViewFees && (
                <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <h2 className="font-semibold text-slate-900">Fee invoices and payments</h2>
                    <div className="mt-4 overflow-x-auto">
                        <table className="w-full min-w-[680px] text-left text-sm">
                            <thead className="border-b border-slate-100 text-xs uppercase text-slate-500"><tr><th className="py-3 pr-4">Invoice</th><th className="py-3 pr-4">Year</th><th className="py-3 pr-4">Issued / due</th><th className="py-3 pr-4">Total</th><th className="py-3 pr-4">Paid</th><th className="py-3">Status</th></tr></thead>
                            <tbody>{invoices.data.map((invoice) => <tr key={invoice.id} className="border-b border-slate-50"><td className="py-3 pr-4">{invoice.number}</td><td className="py-3 pr-4">{invoice.academicYear}</td><td className="py-3 pr-4">{invoice.issuedAt} / {invoice.dueDate}</td><td className="py-3 pr-4">{Number(invoice.total).toFixed(2)}</td><td className="py-3 pr-4">{Number(invoice.paid).toFixed(2)}</td><td className="py-3 capitalize">{invoice.status}<div className="mt-1 text-xs text-slate-500">{invoice.payments.map((payment) => `${payment.date}: ${payment.receipt}`).join(' · ')}</div></td></tr>)}</tbody>
                        </table>
                    </div>
                    {invoices.data.length === 0 && <EmptyMessage>No fee invoices found.</EmptyMessage>}
                    <Pagination links={invoices.links} />
                </section>
            )}

            {activeTab === 'homework' && canViewHomework && (
                <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <h2 className="font-semibold text-slate-900">Homework submissions</h2>
                    <div className="mt-4 overflow-x-auto">
                        <table className="w-full min-w-[620px] text-left text-sm">
                            <thead className="border-b border-slate-100 text-xs uppercase text-slate-500"><tr><th className="py-3 pr-4">Assignment</th><th className="py-3 pr-4">Subject / year</th><th className="py-3 pr-4">Due</th><th className="py-3 pr-4">Submitted</th><th className="py-3">Score / feedback</th></tr></thead>
                            <tbody>{homeworkSubmissions.data.map((submission) => <tr key={submission.id} className="border-b border-slate-50"><td className="py-3 pr-4">{submission.title}</td><td className="py-3 pr-4">{submission.subject} · {submission.academicYear}</td><td className="py-3 pr-4">{submission.dueDate}</td><td className="py-3 pr-4">{submission.submittedAt}</td><td className="py-3">{submission.score === null ? 'Not graded' : `${submission.score} / ${submission.maxPoints}`}{submission.feedback && <div className="mt-1 max-w-xs whitespace-pre-wrap text-xs text-slate-500">{submission.feedback}</div>}</td></tr>)}</tbody>
                        </table>
                    </div>
                    {homeworkSubmissions.data.length === 0 && <EmptyMessage>No homework submissions found.</EmptyMessage>}
                    <Pagination links={homeworkSubmissions.links} />
                </section>
            )}

            {activeTab === 'history' && (
                <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <h2 className="font-semibold text-slate-900">Consolidated student history</h2>
                    <p className="mt-1 text-sm text-slate-500">Academic, attendance, results, fees, homework, documents, and notes in date order.</p>
                    {canViewAuditHistory && (
                        <div className="mt-5 border-b border-slate-100 pb-5">
                            <h3 className="font-medium text-slate-900">Audit ledger</h3>
                            <p className="mt-1 text-sm text-slate-500">Recorded changes to the permanent student record.</p>
                            {auditEntries.data.length === 0 ? (
                                <EmptyMessage>No audited changes have been recorded.</EmptyMessage>
                            ) : (
                                <ol className="mt-4 space-y-3">
                                    {auditEntries.data.map((entry) => (
                                        <li key={entry.id} className="rounded-xl border border-slate-100 p-4">
                                            <div className="flex flex-wrap items-start justify-between gap-2">
                                                <p className="font-medium text-slate-900">{entry.summary}</p>
                                                <span className="text-xs text-slate-500">{entry.occurredAt}</span>
                                            </div>
                                            <p className="mt-1 text-xs capitalize text-slate-500">{entry.eventType.replaceAll('.', ' ').replaceAll('_', ' ')} · by {entry.actor}</p>
                                        </li>
                                    ))}
                                </ol>
                            )}
                            <Pagination links={auditEntries.links} />
                        </div>
                    )}
                    <h3 className="mt-5 font-medium text-slate-900">Recent activity</h3>
                    {history.length === 0 ? <EmptyMessage>No history is available for this student.</EmptyMessage> : (
                        <ol className="mt-5 space-y-4 border-l border-slate-200 pl-5">
                            {history.map((item, index) => <li key={`${item.type}-${item.date}-${index}`} className="relative"><span className="absolute -left-[1.58rem] top-1 size-2.5 rounded-full bg-teal-700 ring-4 ring-white" /><p className="text-xs font-medium text-slate-500">{item.date} · {item.type}</p><p className="mt-1 text-sm font-semibold text-slate-900">{item.title}</p><p className="mt-1 text-sm text-slate-600">{item.detail}</p></li>)}
                        </ol>
                    )}
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

function EmptyMessage({ children }) {
    return <p className="mt-4 rounded-xl bg-slate-50 px-4 py-5 text-sm text-slate-500">{children}</p>;
}

function Pagination({ links }) {
    if (links.length < 4) {
        return null;
    }

    return (
        <nav aria-label="Pagination" className="mt-5 flex flex-wrap gap-2">
            {links.map((link, index) => link.url ? (
                <Link key={index} href={link.url} preserveScroll dangerouslySetInnerHTML={{ __html: link.label }} className={`rounded-lg border px-3 py-2 text-xs font-medium ${link.active ? 'border-teal-800 bg-teal-800 text-white' : 'border-slate-200 text-slate-600 hover:bg-slate-50'}`} />
            ) : <span key={index} dangerouslySetInnerHTML={{ __html: link.label }} className="rounded-lg border border-slate-100 px-3 py-2 text-xs text-slate-400" />)}
        </nav>
    );
}

function GuardianAccountControls({ guardian, studentId, parentAccounts }) {
    const linkForm = useForm({ user_id: '' });
    const unlinkForm = useForm({});
    const inviteForm = useForm({});
    const baseUrl = `/students/${studentId}/guardians/${guardian.id}/account`;

    function linkAccount(event) {
        event.preventDefault();
        linkForm.post(baseUrl, { preserveScroll: true });
    }

    function unlinkAccount() {
        unlinkForm.delete(baseUrl, { preserveScroll: true });
    }

    function inviteAccount() {
        inviteForm.post(`${baseUrl}/invite`, { preserveScroll: true });
    }

    return (
        <div className="mt-3 border-t border-slate-100 pt-3">
            {guardian.userId ? (
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <p className="text-xs text-emerald-700">Portal account linked: {guardian.accountName}</p>
                    <button type="button" onClick={unlinkAccount} disabled={unlinkForm.processing} className="text-xs font-semibold text-rose-700 hover:text-rose-900">Unlink account</button>
                </div>
            ) : (
                <div className="space-y-3">
                    {guardian.email && parentAccounts.length > 0 && (
                        <form onSubmit={linkAccount} className="flex flex-wrap gap-2">
                            <select aria-label={`Parent account for ${guardian.name}`} value={linkForm.data.user_id} onChange={(event) => linkForm.setData('user_id', event.target.value)} className="min-w-0 flex-1 rounded-lg border border-slate-200 px-3 py-2 text-xs" required>
                                <option value="">Select an existing Parent account</option>
                                {parentAccounts.map((account) => <option key={account.id} value={account.id}>{account.name} · {account.email}</option>)}
                            </select>
                            <button disabled={linkForm.processing || !linkForm.data.user_id} className="rounded-lg bg-teal-800 px-3 py-2 text-xs font-semibold text-white disabled:opacity-60">Link</button>
                        </form>
                    )}
                    {guardian.email && <button type="button" onClick={inviteAccount} disabled={inviteForm.processing} className="text-xs font-semibold text-teal-800 hover:text-teal-950">Create Parent account and send setup email</button>}
                    {!guardian.email && <p className="text-xs text-slate-500">Add an email address to invite a portal account.</p>}
                </div>
            )}
            {Object.values(linkForm.errors).concat(Object.values(inviteForm.errors)).map((error, index) => <p key={index} role="alert" className="mt-2 text-xs text-rose-700">{error}</p>)}
        </div>
    );
}
