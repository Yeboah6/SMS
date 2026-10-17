import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import ExamForm from '../../Components/Exams/ExamForm';

export default function ExamsEdit({ exam, terms, classSubjects, selected }) {
    const form = useForm({
        term_id: exam.termId ? String(exam.termId) : '',
        name: exam.name,
        exam_type: exam.examType,
        start_date: exam.startDate,
        end_date: exam.endDate,
        subjects: selected.map((item) => ({
            subject_id: item.subjectId,
            exam_date: item.examDate,
            max_marks: item.maxMarks,
            pass_marks: item.passMarks,
        })),
    });

    function submit(event) {
        event.preventDefault();
        form.put(`/exams/${exam.id}`);
    }

    return (
        <AppLayout title={`Edit ${exam.name}`} description={`${exam.className} · ${exam.academicYear}. Only draft examinations can be edited.`}>
            <Head title={`Edit ${exam.name}`} />
            <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <ExamForm
                    form={form}
                    onSubmit={submit}
                    terms={terms}
                    selectedClass={{ name: exam.className, subjects: classSubjects }}
                    lockedSubjectIds={selected.filter((item) => item.hasMarks).map((item) => item.subjectId)}
                    cancelHref={`/exams/${exam.id}`}
                    submitLabel="Save changes"
                />
            </section>
        </AppLayout>
    );
}