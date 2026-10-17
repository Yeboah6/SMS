import { Head } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import StudentNoteForm from './Form';

export default function StudentNotesEdit({ student, note, today }) {
    return (
        <AppLayout title="Edit student note" description={`${student.name} · ${note.title}`}>
            <Head title={`Edit note · ${student.name}`} />
            <StudentNoteForm student={student} note={note} today={today} />
        </AppLayout>
    );
}
