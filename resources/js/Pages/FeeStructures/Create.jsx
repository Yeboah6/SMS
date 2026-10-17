import { Head, useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import FeesNav from '../../Components/Fees/FeesNav';
import FeeStructureForm from '../../Components/Fees/FeeStructureForm';
import { dueDateAfter } from '../../lib/fees';

export default function FeeStructuresCreate({ academicYear, classes, feeHeads, selectedClassId }) {
    const { feeSettings } = usePage().props;
    const form = useForm({
        academic_year_id: String(academicYear.id),
        class_id: selectedClassId ? String(selectedClassId) : '',
        fee_head_id: '',
        amount: '',
        due_date: dueDateAfter(feeSettings.default_due_days),
    });

    function submit(event) {
        event.preventDefault();
        form.post('/fee-structures');
    }

    return (
        <AppLayout title="Add fee to a class" description={`Academic year ${academicYear.name}. Existing invoices are not changed; generate invoices again to bill new fees.`}>
            <Head title="Add fee to a class" />
            <FeesNav current="structures" yearId={academicYear.id} />
            <section className="max-w-3xl rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                {feeHeads.length === 0 ? (
                    <p className="rounded-xl bg-amber-50 px-3 py-4 text-sm text-amber-900">There are no active fee heads. Add a fee head first (step 1).</p>
                ) : (
                    <FeeStructureForm form={form} onSubmit={submit} classes={classes} feeHeads={feeHeads} cancelHref={`/fee-structures?academic_year_id=${academicYear.id}`} submitLabel="Add fee" />
                )}
            </section>
        </AppLayout>
    );
}