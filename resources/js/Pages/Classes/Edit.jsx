import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import ClassForm from '../../Components/Classes/ClassForm';

export default function ClassesEdit({ schoolClass }) {
    const form = useForm({
        name: schoolClass.name,
        sort_order: String(schoolClass.sortOrder),
    });

    function submit(event) {
        event.preventDefault();
        form.patch(`/classes/${schoolClass.id}`);
    }

    return (
        <AppLayout title={`Edit ${schoolClass.name}`} description="Rename the class or change where it appears in lists.">
            <Head title={`Edit ${schoolClass.name}`} />
            <section className="max-w-2xl rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <ClassForm form={form} onSubmit={submit} submitLabel="Save changes" cancelHref={`/classes/${schoolClass.id}`} />
            </section>
        </AppLayout>
    );
}