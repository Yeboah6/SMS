import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import SuppliesNav from '../../../Components/Supplies/SuppliesNav';
import RequirementForm from '../../../Components/Supplies/RequirementForm';

export default function RequirementsCreate({ academicYear, classes, terms, items, selectedClassId }) {
    const form = useForm({ academic_year_id: academicYear.id, class_id: selectedClassId ? String(selectedClassId) : '', item_id: '', quantity_per_student: 1, term_id: '', is_required: true, notes: '' });

    return (
        <AppLayout title="Add class requirement" description={`Academic year ${academicYear.name}`}>
            <Head title="Add class requirement" />
            <SuppliesNav current="requirements" />
            <section className="max-w-3xl rounded-2xl border border-slate-200 bg-white p-5 sm:p-6"><RequirementForm form={form} onSubmit={(event) => { event.preventDefault(); form.post('/supplies/requirements'); }} classes={classes} terms={terms} items={items} cancelHref={`/supplies/requirements?academic_year_id=${academicYear.id}${selectedClassId ? `&class_id=${selectedClassId}` : ''}`} submitLabel="Add requirement" /></section>
        </AppLayout>
    );
}