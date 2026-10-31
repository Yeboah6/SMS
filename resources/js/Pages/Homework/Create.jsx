import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import AssignmentForm from '../../Components/Homework/AssignmentForm';

export default function HomeworkCreate({ academicYear, subjectAssignments, today }) {
    const first = subjectAssignments[0];
    const form = useForm({ section_id: first?.sectionId || '', subject_id: first?.subjectId || '', title: '', instructions: '', assigned_date: today, due_date: '', max_points: 100 });

    return (
        <AppLayout title="Assign homework" description={`${academicYear ? academicYear.name : 'No active academic year'}. You can assign work to the sections and subjects you teach.`}>
            <Head title="Assign homework" />
            <section className="max-w-3xl rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                {subjectAssignments.length === 0 ? (
                    <p className="rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900">You have no section and subject assignments this year, so there is nothing to assign homework to.</p>
                ) : (
                    <AssignmentForm form={form} onSubmit={(event) => { event.preventDefault(); form.post('/homework'); }} subjectAssignments={subjectAssignments} submitLabel="Publish assignment" cancelHref="/homework" />
                )}
            </section>
        </AppLayout>
    );
}