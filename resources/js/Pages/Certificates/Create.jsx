import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import CertificateForm from '../../Components/Certificates/CertificateForm';

export default function CertificatesCreate({ certificateTypes }) {
    const form = useForm({
        admission_number: '',
        certificate_type: certificateTypes[0] || '',
        reason: '',
        review_comments: '',
    });

    function submit(event) {
        event.preventDefault();
        form.post('/certificates/issue');
    }

    return (
        <AppLayout title="Issue certificate" description="Issue a certificate directly for a student who has not submitted a request.">
            <Head title="Issue certificate" />
            <section className="max-w-3xl rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <p className="mb-5 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    The certificate is issued immediately and gets the next certificate number. An issued certificate cannot be edited or deleted, only revoked.
                </p>
                <CertificateForm form={form} onSubmit={submit} certificateTypes={certificateTypes} submitLabel="Issue certificate" cancelHref="/certificates" showNote />
            </section>
        </AppLayout>
    );
}