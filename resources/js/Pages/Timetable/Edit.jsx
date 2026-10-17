import { Head } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import TimetableSlotForm from '../../Components/Timetables/TimetableSlotForm';

export default function TimetableEdit({ academicYear, slot, assignments, days }) {
    return (
        <AppLayout title="Edit timetable period" description={`${slot.dayName} · ${slot.subject} · ${academicYear.name}`}>
            <Head title="Edit timetable period" />
            <section className="max-w-3xl rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <TimetableSlotForm assignments={assignments} days={days} slot={slot} cancelHref={`/timetable/${slot.id}`} />
            </section>
        </AppLayout>
    );
}