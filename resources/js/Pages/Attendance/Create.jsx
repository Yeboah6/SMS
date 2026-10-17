import { Head } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import AttendanceRegisterForm from '../../Components/Attendance/AttendanceRegisterForm';
import { dateLabel } from '../../lib/attendance';

export default function AttendanceCreate({ register, students }) {
    return (
        <AppLayout title={`Take attendance · ${register.className} ${register.sectionName}`} description={`${dateLabel(register.date)} · ${register.academicYear}. Everyone starts as present.`}>
            <Head title="Take attendance" />
            <AttendanceRegisterForm register={register} students={students} cancelHref={`/attendance?academic_year_id=${register.academicYearId}&date=${register.date}`} />
        </AppLayout>
    );
}