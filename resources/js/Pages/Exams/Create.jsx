import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import ExamForm from '../../Components/Exams/ExamForm';
import { Field, inputClass } from '../Auth/Login';

export default function ExamsCreate({ academicYears, selectedAcademicYearId, terms, classes }) {
    const [classId, setClassId] = useState('');
    const selectedClass = classes.find((item) => String(item.id) === String(classId)) || null;
    const form = useForm({
        academic_year_id: String(selectedAcademicYearId),
        term_id: '',
        class_id: '',
        name: '',
        exam_type: 'unit_test',
        start_date: '',
        end_date: '',
        subjects: [],
    });

    function changeClass(id) {
        setClassId(id);
        form.setData({ ...form.data, class_id: id, subjects: [] });
    }

    function changeYear(yearId) {
        router.get('/exams/create', { academic_year_id: yearId });
    }

    function submit(event) {
        event.preventDefault();
        form.post('/exams');
    }

    return (
        <AppLayout title="New examination" description="Choose class subjects and set their exam dates and scoring thresholds.">
            <Head title="New examination" />

            <section className="mb-6 rounded-2xl border border-slate-200 bg-white p-5">
                <Field label="Academic year">
                    <select value={selectedAcademicYearId} onChange={(event) => changeYear(event.target.value)} className={`${inputClass(false)} sm:max-w-xs`}>
                        {academicYears.map((year) => <option key={year.id} value={year.id}>{year.name} · {year.status}</option>)}
                    </select>
                </Field>
                {form.errors.academic_year_id && <p role="alert" className="mt-2 text-sm text-rose-700">{form.errors.academic_year_id}</p>}
            </section>

            <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <ExamForm
                    form={form}
                    onSubmit={submit}
                    terms={terms}
                    classes={classes}
                    selectedClass={selectedClass}
                    onClassChange={changeClass}
                    cancelHref={`/exams?academic_year_id=${selectedAcademicYearId}`}
                    submitLabel="Create examination"
                />
            </section>
        </AppLayout>
    );
}