import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import ClassForm from '../../Components/Classes/ClassForm';

export default function ClassesCreate({ years, selectedYearId }) {
    const form = useForm({
        academic_year_id: String(selectedYearId || ''),
        name: '',
        sort_order: '0',
    });

    function submit(event) {
        event.preventDefault();
        form.post('/classes');
    }

    const back = form.data.academic_year_id ? `/classes?academic_year_id=${form.data.academic_year_id}` : '/classes';

    return (
        <AppLayout title="New class" description="Add a class to an academic year.">
            <Head title="New class" />
            <section className="max-w-2xl rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <ClassForm form={form} onSubmit={submit} years={years} submitLabel="Add class" cancelHref={back} />
            </section>
        </AppLayout>
    );
}