import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import AdmissionForm from '../../Components/Admissions/AdmissionForm';

export default function AdmissionsEdit({ admission, today }) {
    const form = useForm({
        parent_name: admission.parentName || '',
        parent_phone: admission.parentPhone || '',
        parent_email: admission.parentEmail || '',
        parent_occupation: admission.parentOccupation || '',
        parent_address: admission.parentAddress || '',
        child_first_name: admission.childFirstName || '',
        child_middle_name: admission.childMiddleName || '',
        child_last_name: admission.childLastName || '',
        date_of_birth: admission.dateOfBirth || '',
        gender: admission.gender || 'prefer_not_to_say',
        child_address: admission.childAddress || '',
        applying_for: admission.applyingFor || '',
        source: admission.source || 'walk_in',
    });

    function submit(event) {
        event.preventDefault();
        form.patch(`/admissions/${admission.id}`);
    }

    return (
        <AppLayout title="Edit admission details" description={`${admission.applicationNumber} · Details can be corrected until the application is approved or rejected.`}>
            <Head title="Edit admission details" />
            <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <AdmissionForm form={form} onSubmit={submit} today={today} submitLabel="Save changes" cancelHref={`/admissions/${admission.id}`} />
            </section>
        </AppLayout>
    );
}