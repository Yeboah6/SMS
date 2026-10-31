import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import SuppliesNav from '../../../Components/Supplies/SuppliesNav';
import RequirementForm from '../../../Components/Supplies/RequirementForm';

export default function RequirementsEdit({ requirement, terms, items }) {
    const form = useForm({ item_id: String(requirement.itemId), quantity_per_student: requirement.quantity, term_id: requirement.termId ? String(requirement.termId) : '', is_required: requirement.isRequired, notes: requirement.notes || '' });

    return (
        <AppLayout title="Edit class requirement" description={`${requirement.className} · ${requirement.academicYear}`}>
            <Head title="Edit class requirement" />
            <SuppliesNav current="requirements" />
            <section className="max-w-3xl rounded-2xl border border-slate-200 bg-white p-5 sm:p-6"><RequirementForm form={form} onSubmit={(event) => { event.preventDefault(); form.put(`/supplies/requirements/${requirement.id}`); }} fixed={requirement.className} terms={terms} items={items} cancelHref={`/supplies/requirements?academic_year_id=${requirement.academicYearId}&class_id=${requirement.classId}`} submitLabel="Save changes" /></section>
        </AppLayout>
    );
}