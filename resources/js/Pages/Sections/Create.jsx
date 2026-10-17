import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import SectionForm from '../../Components/SectionForm';

export default function SectionsCreate({ schoolClass }) {
    const form = useForm({ name: '', capacity: '' });

    function submit(event) {
        event.preventDefault();
        form.post(`/classes/${schoolClass.id}/sections`);
    }

    return (
        <AppLayout title={`New section in ${schoolClass.name}`} description="Add a section and optionally set its seat capacity.">
            <Head title="New section" />
            <section className="max-w-2xl rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <SectionForm form={form} onSubmit={submit} submitLabel="Add section" cancelHref={`/classes/${schoolClass.id}`} />
            </section>
        </AppLayout>
    );
}