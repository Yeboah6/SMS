import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import SubjectForm from '../../Components/Subjects/SubjectForm';

export default function SubjectsEdit({ subject }) {
    const form = useForm({
        code: subject.code,
        name: subject.name,
        description: subject.description || '',
    });

    function submit(event) {
        event.preventDefault();
        form.patch(`/subjects/${subject.id}`);
    }

    return (
        <AppLayout title={`Edit ${subject.name}`} description="Update the subject code, name or description.">
            <Head title={`Edit ${subject.name}`} />
            <section className="max-w-2xl rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <SubjectForm form={form} onSubmit={submit} submitLabel="Save changes" cancelHref={`/subjects/${subject.id}`} />
            </section>
        </AppLayout>
    );
}