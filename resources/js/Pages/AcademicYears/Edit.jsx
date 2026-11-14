import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import AcademicYearForm from './Form';

export default function AcademicYearsEdit({ year }) {
    const form = useForm({
        name: year.name,
        start_date: year.startDate,
        end_date: year.endDate,
    });

    function submit(event) {
        event.preventDefault();
        form.patch(`/academic-years/${year.id}`);
    }

    return (
        <AppLayout title={`Edit ${year.name}`} description="Only draft academic years can be edited.">
            <Head title={`Edit ${year.name}`} />
            <Link href={`/academic-years/${year.id}`} className="mb-6 inline-flex items-center gap-2 text-sm font-medium text-slate-600 hover:text-teal-800">
                <ArrowLeft size={16} />
                Back to {year.name}
            </Link>
            <section className="max-w-2xl rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <AcademicYearForm
                    form={form}
                    onSubmit={submit}
                    submitLabel="Save changes"
                    cancelHref={`/academic-years/${year.id}`}
                />
            </section>
        </AppLayout>
    );
}