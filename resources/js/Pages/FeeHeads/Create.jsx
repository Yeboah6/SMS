import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import FeesNav from '../../Components/Fees/FeesNav';
import FeeHeadForm from '../../Components/Fees/FeeHeadForm';

export default function FeeHeadsCreate() {
    const form = useForm({ code: '', name: '', description: '' });

    function submit(event) {
        event.preventDefault();
        form.post('/fee-heads');
    }

    return (
        <AppLayout title="Add fee head" description="Create a reusable charge type.">
            <Head title="Add fee head" />
            <FeesNav current="heads" />
            <section className="max-w-3xl rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <FeeHeadForm form={form} onSubmit={submit} cancelHref="/fee-heads" />
            </section>
        </AppLayout>
    );
}