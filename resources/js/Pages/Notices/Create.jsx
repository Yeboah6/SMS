import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import NoticeForm from '../../Components/Notices/NoticeForm';

export default function NoticesCreate({ canPublish }) {
    const form = useForm({ title: '', body: '', audience: 'all', expires_at: '' });

    function save(publish) {
        // transform() does not return the form in the React adapter, so it is called on its own.
        form.transform((data) => ({ ...data, publish }));
        form.post('/notices', { onFinish: () => form.transform((data) => data) });
    }

    return (
        <AppLayout title="New notice" description="Save a draft to review it first, or publish straight away to notify the audience.">
            <Head title="New notice" />
            <section className="max-w-3xl rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <NoticeForm
                    form={form}
                    onSubmit={(event) => { event.preventDefault(); save(false); }}
                    onSaveAndPublish={canPublish ? () => save(true) : null}
                    submitLabel="Save draft"
                    cancelHref="/notices"
                />
            </section>
        </AppLayout>
    );
}