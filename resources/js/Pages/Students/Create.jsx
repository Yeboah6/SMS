import { Head } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import StudentForm from '../../Components/Students/StudentForm';

export default function StudentsCreate() {
    return (
        <AppLayout title="Add student" description="Create a permanent student profile and guardian records.">
            <Head title="Add student" />
            <StudentForm />
        </AppLayout>
    );
}