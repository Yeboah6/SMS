import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import AcademicYearForm from './Form';

export default function AcademicYearsCreate() {
    const form = useForm({ name: '', start_date: '', end_date: '' });

    function submit(event) {
        event.preventDefault();
        form.post('/academic-years');
    }

    return (
        <AppLayout title="New academic year" description="New years start in Draft. Add terms before activation.">
            <Head title="New academic year" />
            <Link href="/academic-years" className="mb-6 inline-flex items-center gap-2 text-sm font-medium text-slate-600 hover:text-teal-800">
                <ArrowLeft size={16} />
                All academic years
            </Link>
            <section className="max-w-2xl rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <AcademicYearForm form={form} onSubmit={submit} submitLabel="Create draft" />
            </section>
        </AppLayout>
    );
}