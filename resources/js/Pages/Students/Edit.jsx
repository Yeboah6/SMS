import { Head } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import StudentForm from '../../Components/Students/StudentForm';

export default function StudentsEdit({ student }) {
    const name = [student.firstName, student.lastName].filter(Boolean).join(' ');

    return (
        <AppLayout title={`Edit ${name}`} description={`Student profile · ${student.admissionNumber}`}>
            <Head title={`Edit ${name}`} />
            <StudentForm student={student} />
        </AppLayout>
    );
}