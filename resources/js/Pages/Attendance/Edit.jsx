import { Head } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import AttendanceRegisterForm from '../../Components/Attendance/AttendanceRegisterForm';
import { dateLabel } from '../../lib/attendance';

export default function AttendanceEdit({ register, students }) {
    return (
        <AppLayout title={`Edit attendance · ${register.className} ${register.sectionName}`} description={`${dateLabel(register.date)} · ${register.academicYear}`}>
            <Head title="Edit attendance" />
            <AttendanceRegisterForm register={register} students={students} isEditing cancelHref={`/attendance/${register.id}`} />
        </AppLayout>
    );
}