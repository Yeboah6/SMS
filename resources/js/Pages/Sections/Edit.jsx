import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import SectionForm from '../../Components/SectionForm';

export default function SectionsEdit({ schoolClass, section }) {
    const form = useForm({
        name: section.name,
        capacity: section.capacity ? String(section.capacity) : '',
    });

    function submit(event) {
        event.preventDefault();
        form.patch(`/sections/${section.id}`);
    }

    return (
        <AppLayout title={`Edit section ${section.name}`} description={`Part of ${schoolClass.name}`}>
            <Head title={`Edit section ${section.name}`} />
            <section className="max-w-2xl rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <SectionForm form={form} onSubmit={submit} submitLabel="Save changes" cancelHref={`/sections/${section.id}`} />
            </section>
        </AppLayout>
    );
}