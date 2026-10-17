import { Head } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import TimetableSlotForm from '../../Components/Timetables/TimetableSlotForm';

export default function TimetableCreate({ academicYear, assignments, days }) {
    return (
        <AppLayout title="Add timetable period" description={`Academic year ${academicYear.name}. Teacher and section overlaps are checked before a period is saved.`}>
            <Head title="Add timetable period" />
            <section className="max-w-3xl rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                {assignments.length === 0 ? (
                    <p className="rounded-xl bg-slate-50 px-3 py-4 text-sm text-slate-500">No teaching assignments exist for this year yet. Assign subject teachers to sections first.</p>
                ) : (
                    <TimetableSlotForm
                        assignments={assignments}
                        days={days}
                        academicYearId={academicYear.id}
                        cancelHref={`/timetable?academic_year_id=${academicYear.id}`}
                    />
                )}
            </section>
        </AppLayout>
    );
}