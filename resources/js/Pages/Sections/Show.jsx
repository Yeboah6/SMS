import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import StatusBanner from '../../Components/StatusBanner';
import StudentsTable from '../../Components/StudentsTable';
import TeacherAssignments from '../../Components/TeacherAssignments';

export default function SectionsShow({ section, schoolClass, yearLocked, subjects, teachers, students, can }) {
    const editable = !yearLocked;

    function deleteSection() {
        if (window.confirm(`Delete section ${section.name}? Sections with students, academic records, or teacher assignments cannot be deleted.`)) {
            router.delete(`/sections/${section.id}`);
        }
    }

    const capacity = section.capacity
        ? `${students.length} of ${section.capacity} seats filled`
        : 'Capacity not set';

    return (
        <AppLayout title={`${schoolClass.name} · Section ${section.name}`} description={capacity}>
            <Head title={`${schoolClass.name} · Section ${section.name}`} />
            <StatusBanner />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <Link href={`/classes/${schoolClass.id}`} className="text-sm font-semibold text-teal-800 hover:text-teal-950">Back to {schoolClass.name}</Link>
                <div className="flex items-center gap-4 text-sm font-semibold">
                    {can.updateSection && editable && <Link href={`/sections/${section.id}/edit`} className="text-teal-800 hover:text-teal-950">Edit section</Link>}
                    {can.deleteSection && editable && <button type="button" onClick={deleteSection} className="text-rose-700 hover:text-rose-900">Delete section</button>}
                </div>
            </div>

            {can.assignTeachers && editable && (
                <section className="mb-6 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <h2 className="mb-4 font-semibold text-slate-900">Teachers</h2>
                    <TeacherAssignments section={section} subjects={subjects} teachers={teachers} />
                </section>
            )}

            {can.viewStudents && (
                <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <h2 className="mb-4 font-semibold text-slate-900">Students</h2>
                    <StudentsTable students={students} />
                </section>
            )}
        </AppLayout>
    );
}