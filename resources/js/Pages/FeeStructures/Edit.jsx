import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import FeesNav from '../../Components/Fees/FeesNav';
import FeeStructureForm from '../../Components/Fees/FeeStructureForm';

export default function FeeStructuresEdit({ academicYear, feeStructure }) {
    const form = useForm({
        academic_year_id: String(academicYear.id),
        class_id: String(feeStructure.classId),
        fee_head_id: String(feeStructure.feeHeadId),
        amount: feeStructure.amount,
        due_date: feeStructure.dueDate,
    });

    function submit(event) {
        event.preventDefault();
        form.put(`/fee-structures/${feeStructure.id}`);
    }

    return (
        <AppLayout title={`${feeStructure.feeHeadName} · ${feeStructure.className}`} description={`Academic year ${academicYear.name}. Existing invoices remain unchanged.`}>
            <Head title="Edit fee" />
            <FeesNav current="structures" yearId={academicYear.id} />
            <section className="max-w-3xl rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <FeeStructureForm form={form} onSubmit={submit} fixed={feeStructure} cancelHref={`/fee-structures?academic_year_id=${academicYear.id}`} submitLabel="Save fee" />
            </section>
        </AppLayout>
    );
}