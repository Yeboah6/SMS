import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import AdmissionForm from '../../Components/Admissions/AdmissionForm';

export default function AdmissionsCreate({ today }) {
    const form = useForm({
        parent_name: '',
        parent_phone: '',
        parent_email: '',
        parent_occupation: '',
        parent_address: '',
        child_first_name: '',
        child_middle_name: '',
        child_last_name: '',
        date_of_birth: '',
        gender: 'prefer_not_to_say',
        child_address: '',
        applying_for: '',
        source: 'walk_in',
    });

    function submit(event) {
        event.preventDefault();
        form.post('/admissions');
    }

    return (
        <AppLayout title="Record an enquiry" description="Capture family contact details and the requested entry class.">
            <Head title="Record an enquiry" />
            <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <AdmissionForm form={form} onSubmit={submit} today={today} submitLabel="Save enquiry" cancelHref="/admissions" />
            </section>
        </AppLayout>
    );
}