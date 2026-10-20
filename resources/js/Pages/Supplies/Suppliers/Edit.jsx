import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import SuppliesNav from '../../../Components/Supplies/SuppliesNav';
import SupplierForm from '../../../Components/Supplies/SupplierForm';

export default function SuppliersEdit({ supplier }) {
    const form = useForm({ name: supplier.name, contact_person: supplier.contactPerson || '', phone: supplier.phone || '', email: supplier.email || '', address: supplier.address || '', notes: supplier.notes || '', is_active: supplier.isActive });

    return (
        <AppLayout title={`Edit ${supplier.name}`} description="Suppliers are never deleted, so purchase history stays complete. Deactivate one instead.">
            <Head title={`Edit ${supplier.name}`} />
            <SuppliesNav current="suppliers" />
            <section className="max-w-3xl rounded-2xl border border-slate-200 bg-white p-5 sm:p-6"><SupplierForm form={form} onSubmit={(event) => { event.preventDefault(); form.put(`/supplies/suppliers/${supplier.id}`); }} isEditing cancelHref={`/supplies/suppliers/${supplier.id}`} /></section>
        </AppLayout>
    );
}