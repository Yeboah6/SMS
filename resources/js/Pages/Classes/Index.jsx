import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import { Field, inputClass } from '../Auth/Login';

export default function ClassesIndex({ years, selectedYearId, classes, subjects, teachers, can }) {
    const { status } = usePage().props;
    const [yearId, setYearId] = useState(String(selectedYearId || ''));
    const classForm = useForm({
        academic_year_id: String(selectedYearId || ''),
        name: '',
        sort_order: '0',
    });

    function selectYear(event) {
        const nextYear = event.target.value;
        setYearId(nextYear);
        classForm.setData('academic_year_id', nextYear);
        router.get('/classes', nextYear ? { academic_year_id: nextYear } : {}, { preserveState: true, replace: true });
    }

    function createClass(event) {
        event.preventDefault();
        classForm.post('/classes', { onSuccess: () => classForm.reset('name', 'sort_order') });
    }

    return (
        <AppLayout title="Classes & sections" description="Build a year-scoped class structure and assign subjects.">
            <Head title="Classes & sections" />
            {status && <div role="status" className="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{status}</div>}
            <div className="mb-6 flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p className="text-sm font-semibold text-slate-800">Academic year scope</p>
                    <p className="mt-1 text-xs text-slate-500">Classes and sections remain attached to their historical year.</p>
                </div>
                <select
                    value={yearId}
                    onChange={selectYear}
                    aria-label="Select academic year"
                    className="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800"
                >
                    <option value="">Select an academic year</option>
                    {years.map((year) => <option key={year.id} value={year.id}>{year.name} · {year.status}</option>)}
                </select>
            </div>

            {can.create && yearId && (
                <section className="mb-6 rounded-2xl border border-slate-200 bg-white p-5">
                    <h2 className="font-semibold text-slate-900">Create class</h2>
                    <form onSubmit={createClass} className="mt-4 grid gap-4 sm:grid-cols-3">
                        <Field label="Class name" error={classForm.errors.name}>
                            <input value={classForm.data.name} onChange={(event) => classForm.setData('name', event.target.value)} className={inputClass(classForm.errors.name)} placeholder="Class 1" required />
                        </Field>
                        <Field label="Display order" error={classForm.errors.sort_order}>
                            <input type="number" min="0" max="9999" value={classForm.data.sort_order} onChange={(event) => classForm.setData('sort_order', event.target.value)} className={inputClass(classForm.errors.sort_order)} />
                        </Field>
                        <div className="flex items-end">
                            <button disabled={classForm.processing} className="w-full rounded-xl bg-teal-800 px-4 py-3 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-60">Add class</button>
                        </div>
                    </form>
                </section>
            )}

            {!yearId ? (
                <EmptyState title="Select an academic year" detail="Choose a year above to view or create its class structure." />
            ) : classes.length === 0 ? (
                <EmptyState title="No classes for this year" detail="Create a class to start configuring sections and subjects." />
            ) : (
                <div className="space-y-5">
                    {classes.map((schoolClass) => (
                        <ClassCard key={schoolClass.id} schoolClass={schoolClass} subjects={subjects} teachers={teachers} can={can} />
                    ))}
                </div>
            )}
        </AppLayout>
    );
}

function ClassCard({ schoolClass, subjects, teachers, can }) {
    const sectionForm = useForm({ name: '', capacity: '' });
    const subjectForm = useForm({ subject_ids: schoolClass.subjects.map((subject) => subject.id) });

    function createSection(event) {
        event.preventDefault();
        sectionForm.post(`/classes/${schoolClass.id}/sections`, {
            preserveScroll: true,
            onSuccess: () => sectionForm.reset(),
        });
    }

    function saveSubjects(event) {
        event.preventDefault();
        subjectForm.put(`/classes/${schoolClass.id}/subjects`, { preserveScroll: true });
    }

    function toggleSubject(id, checked) {
        subjectForm.setData(
            'subject_ids',
            checked
                ? [...subjectForm.data.subject_ids, id]
                : subjectForm.data.subject_ids.filter((subjectId) => subjectId !== id),
        );
    }

    return (
        <article className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h2 className="text-lg font-semibold text-slate-950">{schoolClass.name}</h2>
                <span className="text-xs text-slate-500">{schoolClass.sections.length} sections · {schoolClass.subjects.length} subjects</span>
            </div>
            <div className="mt-5 grid gap-6 xl:grid-cols-2">
                <section>
                    <h3 className="text-sm font-semibold text-slate-800">Sections</h3>
                    {schoolClass.sections.length ? (
                        <ul className="mt-3 divide-y divide-slate-100 rounded-xl border border-slate-100">
                            {schoolClass.sections.map((section) => (
                                <SectionCard key={section.id} section={section} subjects={schoolClass.subjects} teachers={teachers} can={can} />
                            ))}
                        </ul>
                    ) : (
                        <p className="mt-3 rounded-xl bg-slate-50 px-3 py-4 text-sm text-slate-500">No sections assigned.</p>
                    )}
                    {can.createSection && (
                        <form onSubmit={createSection} className="mt-3 flex flex-wrap items-end gap-2">
                            <label className="min-w-28 flex-1 text-xs font-medium text-slate-600">
                                Section name
                                <input value={sectionForm.data.name} onChange={(event) => sectionForm.setData('name', event.target.value)} className="mt-1.5 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="A" required />
                            </label>
                            <label className="w-28 text-xs font-medium text-slate-600">
                                Capacity
                                <input type="number" min="1" max="5000" value={sectionForm.data.capacity} onChange={(event) => sectionForm.setData('capacity', event.target.value)} className="mt-1.5 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Optional" />
                            </label>
                            <button disabled={sectionForm.processing} className="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Add section</button>
                            {(sectionForm.errors.name || sectionForm.errors.capacity) && <p className="w-full text-xs text-rose-700">{sectionForm.errors.name || sectionForm.errors.capacity}</p>}
                        </form>
                    )}
                </section>

                <section>
                    <h3 className="text-sm font-semibold text-slate-800">Class subjects</h3>
                    {subjects.length === 0 ? (
                        <p className="mt-3 rounded-xl bg-slate-50 px-3 py-4 text-sm text-slate-500">Add subjects before assigning them to this class.</p>
                    ) : (
                        <form onSubmit={saveSubjects} className="mt-3">
                            <div className="grid gap-2 sm:grid-cols-2">
                                {subjects.map((subject) => (
                                    <label key={subject.id} className="flex items-center gap-2 rounded-lg border border-slate-100 px-3 py-2 text-sm text-slate-700">
                                        <input
                                            type="checkbox"
                                            checked={subjectForm.data.subject_ids.includes(subject.id)}
                                            onChange={(event) => toggleSubject(subject.id, event.target.checked)}
                                            disabled={!can.assignSubjects}
                                            className="size-4 rounded border-slate-300 text-teal-700 focus:ring-teal-700"
                                        />
                                        <span>{subject.name}</span>
                                    </label>
                                ))}
                            </div>
                            {can.assignSubjects && (
                                <button disabled={subjectForm.processing} className="mt-3 rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Save subjects</button>
                            )}
                            {subjectForm.errors.subject_ids && <p className="mt-2 text-xs text-rose-700">{subjectForm.errors.subject_ids}</p>}
                        </form>
                    )}
                </section>
            </div>
        </article>
    );
}

function SectionCard({ section, subjects, teachers, can }) {
    const classTeacherForm = useForm({ staff_id: '' });
    const subjectTeacherForm = useForm({ staff_id: '', subject_id: '' });

    function assignClassTeacher(event) {
        event.preventDefault();
        classTeacherForm.post(`/sections/${section.id}/teacher-assignments`, {
            preserveScroll: true,
            onSuccess: () => classTeacherForm.reset(),
        });
    }

    function assignSubjectTeacher(event) {
        event.preventDefault();
        subjectTeacherForm.post(`/sections/${section.id}/subject-assignments`, {
            preserveScroll: true,
            onSuccess: () => subjectTeacherForm.reset(),
        });
    }

    return (
        <li className="px-3 py-3">
            <div className="flex items-center justify-between gap-3 text-sm">
                <span className="font-medium text-slate-800">{section.name}</span>
                <span className="shrink-0 text-xs text-slate-500">{section.capacity ? `${section.capacity} seats` : 'Capacity not set'}</span>
            </div>
            {can.assignTeachers && (
                <div className="mt-3 grid gap-3 lg:grid-cols-2">
                    <div>
                        <p className="text-xs font-semibold text-slate-600">Class teachers</p>
                        <p className="mt-1 text-xs text-slate-500">
                            {section.teacherAssignments.length
                                ? section.teacherAssignments.map((assignment) => assignment.name).join(', ')
                                : 'No teachers assigned'}
                        </p>
                        <form onSubmit={assignClassTeacher} className="mt-2 flex gap-2">
                            <select value={classTeacherForm.data.staff_id} onChange={(event) => classTeacherForm.setData('staff_id', event.target.value)} className="min-w-0 flex-1 rounded-lg border border-slate-200 px-2 py-2 text-xs" required>
                                <option value="">Select teacher</option>
                                {teachers.map((teacher) => <option key={teacher.id} value={teacher.id}>{teacher.name} · {teacher.staffNumber}</option>)}
                            </select>
                            <button disabled={classTeacherForm.processing || teachers.length === 0} className="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 disabled:opacity-50">Assign</button>
                        </form>
                        {classTeacherForm.errors.staff_id && <p role="alert" className="mt-1 text-xs text-rose-700">{classTeacherForm.errors.staff_id}</p>}
                    </div>
                    {subjects.length > 0 && (
                        <div>
                            <p className="text-xs font-semibold text-slate-600">Subject teachers</p>
                            <p className="mt-1 text-xs text-slate-500">
                                {section.subjectAssignments.length
                                    ? section.subjectAssignments.map((assignment) => `${assignment.subjectName}: ${assignment.staffName}`).join(' · ')
                                    : 'No subject teachers assigned'}
                            </p>
                            <form onSubmit={assignSubjectTeacher} className="mt-2 flex flex-wrap gap-2">
                                <select value={subjectTeacherForm.data.staff_id} onChange={(event) => subjectTeacherForm.setData('staff_id', event.target.value)} className="min-w-0 flex-1 rounded-lg border border-slate-200 px-2 py-2 text-xs" required>
                                    <option value="">Select teacher</option>
                                    {teachers.map((teacher) => <option key={teacher.id} value={teacher.id}>{teacher.name}</option>)}
                                </select>
                                <select value={subjectTeacherForm.data.subject_id} onChange={(event) => subjectTeacherForm.setData('subject_id', event.target.value)} className="min-w-0 flex-1 rounded-lg border border-slate-200 px-2 py-2 text-xs" required>
                                    <option value="">Select subject</option>
                                    {subjects.map((subject) => <option key={subject.id} value={subject.id}>{subject.name}</option>)}
                                </select>
                                <button disabled={subjectTeacherForm.processing || teachers.length === 0} className="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 disabled:opacity-50">Assign</button>
                            </form>
                            {(subjectTeacherForm.errors.staff_id || subjectTeacherForm.errors.subject_id) && <p role="alert" className="mt-1 text-xs text-rose-700">{subjectTeacherForm.errors.staff_id || subjectTeacherForm.errors.subject_id}</p>}
                        </div>
                    )}
                </div>
            )}
        </li>
    );
}

function EmptyState({ title, detail }) {
    return (
        <div className="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center">
            <p className="font-medium text-slate-800">{title}</p>
            <p className="mt-1 text-sm text-slate-500">{detail}</p>
        </div>
    );
}
