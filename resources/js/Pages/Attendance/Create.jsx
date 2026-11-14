import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import AttendanceRegisterForm from '../../Components/Attendance/AttendanceRegisterForm';
import { dateLabel } from '../../lib/attendance';

export default function AttendanceCreate({ register, students }) {
    const backHref = `/attendance?academic_year_id=${register.academicYearId}&date=${register.date}`;

    return (
        <AppLayout title={`Take attendance · ${register.className} ${register.sectionName}`} description={`${dateLabel(register.date)} · ${register.academicYear}. Everyone starts as present.`}>
            <Head title="Take attendance" />

            <div className="mb-6">
                <Link
                    href={backHref}
                    className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-teal-200 hover:bg-teal-50 hover:text-teal-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-600 focus-visible:ring-offset-2"
                >
                    <ArrowLeft size={16} aria-hidden="true" />
                    Back to attendance
                </Link>
            </div>

            <AttendanceRegisterForm register={register} students={students} cancelHref={backHref} />
        </AppLayout>
    );
}