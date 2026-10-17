import { Head } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import StudentNoteForm from './Form';

export default function StudentNotesCreate({ student, today }) {
    return (
        <AppLayout title="Add student note" description={student.name}>
            <Head title={`Add note · ${student.name}`} />
            <StudentNoteForm student={student} today={today} />
        </AppLayout>
    );
}
