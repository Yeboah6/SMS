import { Head, Link, router, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import EmptyState from '../../Components/EmptyState';
import StatusBanner from '../../Components/StatusBanner';
import StudentsTable from '../../Components/StudentsTable';

export default function ClassesShow({ schoolClass, yearLocked, sections, subjects, availableSubjects, students, can }) {
    const subjectForm = useForm({ subject_ids: subjects.map((subject) => subject.id) });
    const indexUrl = schoolClass.yearId ? `/classes?academic_year_id=${schoolClass.yearId}` : '/classes';
    const editable = !yearLocked;

    function deleteClass() {
        if (window.confirm(`Delete ${schoolClass.name}? Classes with sections, subject mappings, or historical records cannot be deleted.`)) {
            router.delete(`/classes/${schoolClass.id}`);
        }
    }

    function deleteSection(section) {
        if (window.confirm(`Delete section ${section.name}? Sections with students, academic records, or teacher assignments cannot be deleted.`)) {
            router.delete(`/sections/${section.id}`, { preserveScroll: true });
        }
    }

    function toggleSubject(id, checked) {
        subjectForm.setData(
            'subject_ids',
            checked ? [...subjectForm.data.subject_ids, id] : subjectForm.data.subject_ids.filter((subjectId) => subjectId !== id),
        );
    }

    function saveSubjects(event) {
        event.preventDefault();
        subjectForm.put(`/classes/${schoolClass.id}/subjects`, { preserveScroll: true });
    }

    return (
        <AppLayout title={schoolClass.name} description={schoolClass.yearName ? `Academic year ${schoolClass.yearName}` : 'Class details'}>
            <Head title={schoolClass.name} />
            <StatusBanner />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <Link href={indexUrl} className="text-sm font-semibold text-teal-800 hover:text-teal-950">Back to classes</Link>
                <div className="flex items-center gap-4 text-sm font-semibold">
                    {can.update && editable && <Link href={`/classes/${schoolClass.id}/edit`} className="text-teal-800 hover:text-teal-950">Edit class</Link>}
                    {can.delete && editable && <button type="button" onClick={deleteClass} className="text-rose-700 hover:text-rose-900">Delete class</button>}
                </div>
            </div>

            <div className="grid gap-6 xl:grid-cols-2">
                <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <div className="flex items-center justify-between gap-3">
                        <h2 className="font-semibold text-slate-900">Sections</h2>
                        {can.createSection && editable && (
                            <Link href={`/classes/${schoolClass.id}/sections/create`} className="rounded-lg bg-teal-800 px-3 py-2 text-xs font-semibold text-white hover:bg-teal-900">Add section</Link>
                        )}
                    </div>
                    {sections.length === 0 ? (
                        <p className="mt-4 rounded-xl bg-slate-50 px-3 py-4 text-sm text-slate-500">No sections assigned.</p>
                    ) : (
                        <ul className="mt-4 divide-y divide-slate-100 rounded-xl border border-slate-100">
                            {sections.map((section) => (
                                <li key={section.id} className="flex flex-wrap items-center justify-between gap-3 px-3 py-3 text-sm">
                                    <div>
                                        <Link href={`/sections/${section.id}`} className="font-medium text-slate-900 hover:text-teal-800">Section {section.name}</Link>
                                        <p className="mt-0.5 text-xs text-slate-500">
                                            {section.studentsCount} {section.studentsCount === 1 ? 'student' : 'students'} · {section.capacity ? `${section.capacity} seats` : 'Capacity not set'}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-3 text-xs font-semibold">
                                        <Link href={`/sections/${section.id}`} className="text-teal-800 hover:text-teal-950">View</Link>
                                        {can.updateSection && editable && <Link href={`/sections/${section.id}/edit`} className="text-teal-800 hover:text-teal-950">Edit</Link>}
                                        {can.deleteSection && editable && <button type="button" onClick={() => deleteSection(section)} className="text-rose-700 hover:text-rose-900">Delete</button>}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <h2 className="font-semibold text-slate-900">Class subjects</h2>
                    {availableSubjects.length === 0 ? (
                        <p className="mt-4 rounded-xl bg-slate-50 px-3 py-4 text-sm text-slate-500">Add subjects before assigning them to this class.</p>
                    ) : (
                        <form onSubmit={saveSubjects} className="mt-4">
                            <div className="grid gap-2 sm:grid-cols-2">
                                {availableSubjects.map((subject) => (
                                    <label key={subject.id} className="flex items-center gap-2 rounded-lg border border-slate-100 px-3 py-2 text-sm text-slate-700">
                                        <input
                                            type="checkbox"
                                            checked={subjectForm.data.subject_ids.includes(subject.id)}
                                            onChange={(event) => toggleSubject(subject.id, event.target.checked)}
                                            disabled={!can.assignSubjects || !editable}
                                            className="size-4 rounded border-slate-300 text-teal-700 focus:ring-teal-700"
                                        />
                                        <span>{subject.name}</span>
                                    </label>
                                ))}
                            </div>
                            {can.assignSubjects && editable && (
                                <button disabled={subjectForm.processing} className="mt-3 rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50">Save subjects</button>
                            )}
                            {subjectForm.errors.subject_ids && <p role="alert" className="mt-2 text-xs text-rose-700">{subjectForm.errors.subject_ids}</p>}
                        </form>
                    )}
                </section>
            </div>

            {can.viewStudents && (
                <section className="mt-6 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <h2 className="mb-4 font-semibold text-slate-900">Students in {schoolClass.name}</h2>
                    {sections.length === 0
                        ? <EmptyState title="No sections yet" detail="Create a section to start enrolling students." />
                        : <StudentsTable students={students} showSection />}
                </section>
            )}
        </AppLayout>
    );
}