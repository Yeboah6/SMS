import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import SuppliesNav from '../../../Components/Supplies/SuppliesNav';
import SupplierForm from '../../../Components/Supplies/SupplierForm';

export default function SuppliersCreate() {
    const form = useForm({ name: '', contact_person: '', phone: '', email: '', address: '', notes: '' });

    return (
        <AppLayout title="Add supplier" description="Save the details of a supplier you buy stock from.">
            <Head title="Add supplier" />
            <SuppliesNav current="suppliers" />
            <section className="max-w-3xl rounded-2xl border border-slate-200 bg-white p-5 sm:p-6"><SupplierForm form={form} onSubmit={(event) => { event.preventDefault(); form.post('/supplies/suppliers'); }} cancelHref="/supplies/suppliers" /></section>
        </AppLayout>
    );
}