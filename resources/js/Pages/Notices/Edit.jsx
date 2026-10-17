import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import NoticeForm from '../../Components/Notices/NoticeForm';

export default function NoticesEdit({ notice }) {
    const form = useForm({
        title: notice.title,
        body: notice.body,
        audience: notice.audience,
        expires_at: notice.expiresAt || '',
    });

    function submit(event) {
        event.preventDefault();
        form.put(`/notices/${notice.id}`);
    }

    return (
        <AppLayout title="Edit draft" description="Only drafts can be edited. Once published, a notice can only be withdrawn.">
            <Head title="Edit draft" />
            <section className="max-w-3xl rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <NoticeForm form={form} onSubmit={submit} submitLabel="Save changes" cancelHref={`/notices/${notice.id}`} />
            </section>
        </AppLayout>
    );
}