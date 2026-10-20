import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import SuppliesNav from '../../../Components/Supplies/SuppliesNav';
import ItemForm from '../../../Components/Supplies/ItemForm';

export default function ItemsEdit({ item, categories }) {
    const form = useForm({
        name: item.name, sku: item.sku, category_id: String(item.categoryId), unit: item.unit, cost_price: item.costPrice, selling_price: item.sellingPrice,
        reorder_level: item.reorderLevel, description: item.description || '', is_active: item.isActive,
    });

    return (
        <AppLayout title={`Edit ${item.name}`} description="A price change applies to new sales only. Past sales keep the price they were sold at.">
            <Head title={`Edit ${item.name}`} />
            <SuppliesNav current="items" />
            <section className="max-w-3xl rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <p className="mb-5 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600">Stock on hand is {item.quantity} {item.unit}. To change it, record a movement on the adjust page so the ledger stays complete.</p>
                <ItemForm form={form} onSubmit={(event) => { event.preventDefault(); form.put(`/supplies/items/${item.id}`); }} categories={categories} isEditing cancelHref={`/supplies/items/${item.id}`} />
            </section>
        </AppLayout>
    );
}