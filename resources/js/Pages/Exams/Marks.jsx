import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import PageAlerts from '../../Components/Exams/PageAlerts';
import MarkEntryForm from '../../Components/Exams/MarkEntryForm';
import { Field, inputClass } from '../Auth/Login';
import { dateLabel } from '../../lib/exams';

export default function ExamsMarks({ exam, subject, sections, selectedSectionId, roster, canRecordMarks }) {
    function selectSection(sectionId) {
        router.get(`/exams/${exam.id}/subjects/${subject.id}/marks`, sectionId ? { section_id: sectionId } : {}, { preserveScroll: true });
    }

    return (
        <AppLayout title={`${subject.name} marks`} description={`${exam.name} · ${exam.className} · ${exam.academicYear}`}>
            <Head title={`${subject.name} marks`} />
            <PageAlerts />

            <div className="mb-6">
                <Link href={`/exams/${exam.id}`} className="text-sm font-semibold text-teal-800 hover:text-teal-950">Back to {exam.name}</Link>
            </div>

            <section className="mb-6 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <div className="grid gap-4 sm:grid-cols-3 sm:items-end">
                    <Field label="Section">
                        <select value={selectedSectionId || ''} onChange={(event) => selectSection(event.target.value)} className={inputClass(false)}>
                            {sections.map((section) => <option key={section.id} value={section.id}>{section.name}</option>)}
                        </select>
                    </Field>
                    <p className="text-sm text-slate-500">Exam date {dateLabel(subject.examDate)} · Max {subject.maxMarks} · Pass {subject.passMarks}</p>
                    <p className="text-sm text-slate-500">
                        {canRecordMarks ? 'Marks can be edited while the examination is a draft.' : 'Marks are read-only. Results are published or the year is locked.'}
                    </p>
                </div>
            </section>

            {selectedSectionId ? (
                <MarkEntryForm
                    key={`${exam.id}-${subject.id}-${selectedSectionId}`}
                    exam={exam}
                    subject={subject}
                    sectionId={selectedSectionId}
                    roster={roster}
                    canRecordMarks={canRecordMarks}
                />
            ) : (
                <p className="rounded-xl bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">This class has no sections yet.</p>
            )}
        </AppLayout>
    );
}