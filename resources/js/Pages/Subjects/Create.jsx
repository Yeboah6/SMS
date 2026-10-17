import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import SubjectForm from '../../Components/Subjects/SubjectForm';

export default function SubjectsCreate() {
    const form = useForm({ code: '', name: '', description: '' });

    function submit(event) {
        event.preventDefault();
        form.post('/subjects');
    }

    return (
        <AppLayout title="New subject" description="Add a subject to the school catalog.">
            <Head title="New subject" />
            <section className="max-w-2xl rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <SubjectForm form={form} onSubmit={submit} submitLabel="Create subject" cancelHref="/subjects" />
            </section>
        </AppLayout>
    );
}