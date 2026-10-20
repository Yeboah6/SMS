import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import SuppliesNav from '../../../Components/Supplies/SuppliesNav';
import ItemForm from '../../../Components/Supplies/ItemForm';

export default function ItemsCreate({ categories }) {
    const form = useForm({ name: '', sku: '', category_id: '', unit: 'piece', cost_price: '', selling_price: '', reorder_level: 5, opening_quantity: 0, description: '', is_active: true });

    return (
        <AppLayout title="Add item" description="Opening stock is recorded in the stock ledger.">
            <Head title="Add item" />
            <SuppliesNav current="items" />
            <section className="max-w-3xl rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <ItemForm form={form} onSubmit={(event) => { event.preventDefault(); form.post('/supplies/items'); }} categories={categories} cancelHref="/supplies/items" />
            </section>
        </AppLayout>
    );
}