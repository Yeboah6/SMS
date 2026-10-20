import { Head, Link, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AppLayout from '../../../Layouts/AppLayout';
import SuppliesNav from '../../../Components/Supplies/SuppliesNav';
import { Field, inputClass } from '../../Auth/Login';
import { useMoney } from '../../../lib/supplies';

export default function PurchasesCreate({ suppliers, items, preselectedItemId, today }) {
    const money = useMoney();
    const byId = useMemo(() => Object.fromEntries(items.map((item) => [item.id, item])), [items]);
    const preselected = preselectedItemId ? byId[preselectedItemId] : null;
    const form = useForm({
        supplier_id: '', purchase_date: today, reference: '', notes: '', update_cost_price: true,
        items: preselected ? [{ item_id: preselected.id, quantity: 1, unit_cost: preselected.costPrice }] : [],
    });
    const [pick, setPick] = useState('');
    const total = form.data.items.reduce((sum, line) => sum + Number(line.quantity || 0) * Number(line.unit_cost || 0), 0);

    function addLine() {
        const item = byId[pick];
        if (!item || form.data.items.some((line) => line.item_id === item.id)) return;
        form.setData('items', [...form.data.items, { item_id: item.id, quantity: 1, unit_cost: item.costPrice }]);
        setPick('');
    }

    function setLine(index, key, value) {
        form.setData('items', form.data.items.map((line, position) => (position === index ? { ...line, [key]: value } : line)));
    }

    return (
        <AppLayout title="Receive stock" description="Record a delivery from a supplier. Stock is added and each line is written to the stock ledger.">
            <Head title="Receive stock" />
            <SuppliesNav current="purchases" />
            <form onSubmit={(event) => { event.preventDefault(); form.post('/supplies/purchases'); }} className="grid max-w-5xl gap-6">
                <section className="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 sm:grid-cols-2 sm:p-6">
                    <Field label="Supplier" error={form.errors.supplier_id}><select value={form.data.supplier_id} onChange={(event) => form.setData('supplier_id', event.target.value)} className={inputClass(form.errors.supplier_id)} required><option value="">Select supplier</option>{suppliers.map((supplier) => <option key={supplier.id} value={supplier.id}>{supplier.name}</option>)}</select></Field>
                    <Field label="Delivery date" error={form.errors.purchase_date}><input type="date" max={today} value={form.data.purchase_date} onChange={(event) => form.setData('purchase_date', event.target.value)} className={inputClass(form.errors.purchase_date)} required /></Field>
                    <Field label="Supplier invoice / delivery no." error={form.errors.reference}><input value={form.data.reference} onChange={(event) => form.setData('reference', event.target.value)} className={inputClass(form.errors.reference)} maxLength={100} /></Field>
                    <Field label="Notes" error={form.errors.notes}><input value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} className={inputClass(form.errors.notes)} maxLength={2000} /></Field>
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <h2 className="font-semibold text-slate-900">Items received</h2>
                    <div className="mt-4 flex flex-wrap items-end gap-3">
                        <div className="min-w-60 flex-1"><Field label="Add an item"><select value={pick} onChange={(event) => setPick(event.target.value)} className={inputClass(false)}><option value="">Select item</option>{items.filter((item) => !form.data.items.some((line) => line.item_id === item.id)).map((item) => <option key={item.id} value={item.id}>{item.name} ({item.sku})</option>)}</select></Field></div>
                        <button type="button" disabled={!pick} onClick={addLine} className="rounded-xl border border-teal-800 px-4 py-3 text-sm font-semibold text-teal-800 hover:bg-teal-50 disabled:opacity-50">Add</button>
                    </div>
                    {form.errors.items && <p role="alert" className="mt-3 text-sm text-rose-700">{form.errors.items}</p>}
                    {form.data.items.length === 0 ? <p className="mt-4 rounded-xl bg-slate-50 px-3 py-4 text-sm text-slate-500">No items added yet.</p> : (
                        <div className="mt-4 overflow-x-auto">
                            <table className="w-full min-w-[640px] text-left text-sm">
                                <thead className="border-b border-slate-100 text-xs text-slate-500"><tr><th className="py-3 pr-4">Item</th><th className="py-3 pr-4">Quantity</th><th className="py-3 pr-4">Unit cost</th><th className="py-3 pr-4 text-right">Line total</th><th /></tr></thead>
                                <tbody className="divide-y divide-slate-100">
                                    {form.data.items.map((line, index) => {
                                        const item = byId[line.item_id];
                                        return (
                                            <tr key={line.item_id}>
                                                <td className="py-3 pr-4 font-medium text-slate-800">{item?.name}<span className="block text-xs font-normal text-slate-500">{item?.quantity} {item?.unit} on hand</span></td>
                                                <td className="py-3 pr-4"><input type="number" min="1" value={line.quantity} onChange={(event) => setLine(index, 'quantity', event.target.value)} className="w-24 rounded-lg border border-slate-200 px-2 py-2 text-sm" aria-label={`Quantity of ${item?.name}`} />{form.errors[`items.${index}.quantity`] && <p className="mt-1 text-xs text-rose-700">{form.errors[`items.${index}.quantity`]}</p>}</td>
                                                <td className="py-3 pr-4"><input type="number" min="0" step="0.01" value={line.unit_cost} onChange={(event) => setLine(index, 'unit_cost', event.target.value)} className="w-28 rounded-lg border border-slate-200 px-2 py-2 text-sm" aria-label={`Unit cost of ${item?.name}`} />{form.errors[`items.${index}.unit_cost`] && <p className="mt-1 text-xs text-rose-700">{form.errors[`items.${index}.unit_cost`]}</p>}</td>
                                                <td className="py-3 pr-4 text-right text-slate-900">{money(Number(line.quantity || 0) * Number(line.unit_cost || 0))}</td>
                                                <td className="py-3 text-right"><button type="button" onClick={() => form.setData('items', form.data.items.filter((_, position) => position !== index))} className="text-xs font-semibold text-rose-700 hover:text-rose-900">Remove</button></td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                                <tfoot><tr><td colSpan={3} className="pt-4 text-right text-sm font-semibold text-slate-700">Total cost</td><td className="pt-4 text-right text-base font-semibold text-slate-900">{money(total)}</td><td /></tr></tfoot>
                            </table>
                        </div>
                    )}
                    <label className="mt-5 flex items-start gap-3 text-sm text-slate-700"><input type="checkbox" checked={form.data.update_cost_price} onChange={(event) => form.setData('update_cost_price', event.target.checked)} className="mt-0.5 size-4 rounded border-slate-300 text-teal-700 focus:ring-teal-700" /><span>Use these unit costs as each item's new cost price<span className="block text-xs text-slate-500">Stock value and margin reports use the item's cost price.</span></span></label>
                </section>

                <div className="flex flex-wrap gap-3"><button disabled={form.processing || form.data.items.length === 0} className="rounded-xl bg-teal-800 px-5 py-3 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-60">{form.processing ? 'Saving…' : 'Receive stock'}</button><Link href="/supplies/purchases" className="rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</Link></div>
            </form>
        </AppLayout>
    );
}