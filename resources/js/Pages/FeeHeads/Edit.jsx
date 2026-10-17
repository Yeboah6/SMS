import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import FeesNav from '../../Components/Fees/FeesNav';
import FeeHeadForm from '../../Components/Fees/FeeHeadForm';

export default function FeeHeadsEdit({ feeHead }) {
    const form = useForm({
        code: feeHead.code,
        name: feeHead.name,
        description: feeHead.description || '',
        is_active: feeHead.isActive,
    });

    function submit(event) {
        event.preventDefault();
        form.put(`/fee-heads/${feeHead.id}`);
    }

    return (
        <AppLayout title={`Edit ${feeHead.name}`} description="Update the code, name or availability of this fee head.">
            <Head title={`Edit ${feeHead.name}`} />
            <FeesNav current="heads" />
            <section className="max-w-3xl rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <FeeHeadForm form={form} onSubmit={submit} isEditing cancelHref={`/fee-heads/${feeHead.id}`} />
            </section>
        </AppLayout>
    );
}