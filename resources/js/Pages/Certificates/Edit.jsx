import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import CertificateForm from '../../Components/Certificates/CertificateForm';

export default function CertificatesEdit({ certificateRequest, certificateTypes }) {
    const form = useForm({
        admission_number: certificateRequest.admissionNumber,
        certificate_type: certificateRequest.certificateType,
        reason: certificateRequest.reason,
    });

    function submit(event) {
        event.preventDefault();
        form.put(`/certificates/${certificateRequest.id}`);
    }

    return (
        <AppLayout title="Edit certificate request" description="Only pending requests can be corrected.">
            <Head title="Edit certificate request" />
            <section className="max-w-3xl rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <CertificateForm
                    form={form}
                    onSubmit={submit}
                    certificateTypes={certificateTypes}
                    submitLabel="Save changes"
                    cancelHref={`/certificates/${certificateRequest.id}`}
                    studentName={certificateRequest.studentName}
                />
            </section>
        </AppLayout>
    );
}