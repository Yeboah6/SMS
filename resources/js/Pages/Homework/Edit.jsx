import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import AssignmentForm from '../../Components/Homework/AssignmentForm';

export default function HomeworkEdit({ assignment }) {
    const form = useForm({ title: assignment.title, instructions: assignment.instructions, assigned_date: assignment.assignedDate, due_date: assignment.dueDate, max_points: assignment.maxPoints });

    return (
        <AppLayout title={`Edit ${assignment.title}`} description="Change the details, or move the due date to close or reopen submissions.">
            <Head title={`Edit ${assignment.title}`} />
            <section className="max-w-3xl rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <AssignmentForm form={form} onSubmit={(event) => { event.preventDefault(); form.put(`/homework/${assignment.id}`); }} fixedScope={`${assignment.subject} · ${assignment.section}`} submitLabel="Save changes" cancelHref={`/homework/${assignment.id}`} />
            </section>
        </AppLayout>
    );
}