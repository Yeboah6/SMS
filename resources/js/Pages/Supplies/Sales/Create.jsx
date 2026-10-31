import { Head, Link, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AppLayout from '../../../Layouts/AppLayout';
import PageAlerts from '../../../Components/Exams/PageAlerts';
import SuppliesNav from '../../../Components/Supplies/SuppliesNav';
import Pill from '../../../Components/Supplies/Pill';
import StudentSearchPicker from '../../../Components/Supplies/StudentSearchPicker';
import { Field, inputClass } from '../../Auth/Login';
import { STOCK_STATUS, useMoney } from '../../../lib/supplies';

/**
 * The multi-item sale screen. Totals shown here are a convenience: the server re-reads every price and
 * re-checks stock, discount rights and payment rules before anything is saved.
 */
export default function SalesCreate({ items, student: initialStudent, paymentMethods, policy, idempotencyKey }) {
    const money = useMoney();
    const byId = useMemo(() => Object.fromEntries(items.map((item) => [item.id, item])), [items]);
    const [student, setStudent] = useState(initialStudent);
    const [search, setSearch] = useState('');
    const form = useForm({
        student_id: initialStudent?.id || '',
        items: [],
        discount_type: '',
        discount_value: '',
        override_stock: false,
        notes: '',
        payment: { amount: '', payment_method: paymentMethods[0]?.key || 'cash', transaction_reference: '', idempotency_key: idempotencyKey },
    });

    const lines = form.data.items.map((line) => ({ ...line, item: byId[line.item_id] })).filter((line) => line.item);
    const subtotal = lines.reduce((sum, line) => sum + line.item.sellingPrice * Number(line.quantity || 0), 0);
    const discountValue = Number(form.data.discount_value || 0);
    const discount = !form.data.discount_type || discountValue <= 0 ? 0 : Math.min(subtotal, form.data.discount_type === 'percent' ? subtotal * Math.min(discountValue, 100) / 100 : discountValue);
    const total = Math.max(0, subtotal - discount);
    const shortages = lines.filter((line) => Number(line.quantity) > line.item.quantity);
    const blocked = shortages.length > 0 && !(policy.canOverride && form.data.override_stock);

    const visible = items.filter((item) => item.isActive && (search.trim() === '' || `${item.name} ${item.sku}`.toLowerCase().includes(search.trim().toLowerCase()))).slice(0, 40);

    function add(item) {
        const existing = form.data.items.find((line) => line.item_id === item.id);
        form.setData('items', existing
            ? form.data.items.map((line) => (line.item_id === item.id ? { ...line, quantity: Number(line.quantity) + 1 } : line))
            : [...form.data.items, { item_id: item.id, quantity: 1 }]);
    }

    function setQuantity(itemId, value) {
        form.setData('items', form.data.items.map((line) => (line.item_id === itemId ? { ...line, quantity: value } : line)));
    }

    function setPayment(key, value) {
        form.setData('payment', { ...form.data.payment, [key]: value });
    }

    function pick(next) {
        setStudent(next);
        form.setData('student_id', next?.id || '');
    }

    function submit(event) {
        event.preventDefault();
        // transform() does not return the form in the React adapter, so it is called on its own.
        form.transform((data) => ({
            ...data,
            payment: { ...data.payment, amount: policy.allowPartial ? data.payment.amount : total.toFixed(2) },
        }));
        form.post('/supplies/sales', { onFinish: () => form.transform((data) => data) });
    }

    const paying = policy.allowPartial ? Number(form.data.payment.amount || 0) : total;

    return (
        <AppLayout title="New sale" description="Sell books and supplies to a student. Stock is updated when the sale is saved.">
            <Head title="New sale" />
            <PageAlerts />
            <SuppliesNav current="sales" />

            {!policy.hasActiveYear && <p role="alert" className="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">There is no active academic year, so sales cannot be recorded yet.</p>}

            <form onSubmit={submit} className="grid gap-6 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)]">
                <div className="grid content-start gap-6">
                    <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                        <StudentSearchPicker value={student} onChange={pick} error={form.errors.student_id} />
                        {student && <p className="mt-3 text-sm"><Link href={`/supplies/students/${student.id}`} className="font-semibold text-teal-800 hover:text-teal-950">View purchase history and class requirements</Link></p>}
                    </section>

                    <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                        <h2 className="font-semibold text-slate-900">Items</h2>
                        <input type="search" aria-label="Find an item" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Find an item by name or SKU" className="mt-3 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" />
                        <ul className="mt-3 grid max-h-[26rem] gap-2 overflow-auto sm:grid-cols-2">
                            {visible.map((item) => (
                                <li key={item.id}>
                                    <button type="button" onClick={() => add(item)} className="flex h-full w-full items-start justify-between gap-3 rounded-xl border border-slate-200 p-3 text-left hover:border-teal-300 hover:bg-teal-50/40">
                                        <span><span className="block text-sm font-medium text-slate-900">{item.name}</span><span className="block text-xs text-slate-500">{item.sku} · {item.quantity} {item.unit} left</span></span>
                                        <span className="flex shrink-0 flex-col items-end gap-1"><span className="text-sm font-semibold text-slate-900">{money(item.sellingPrice)}</span>{item.stockStatus !== 'ok' && <Pill map={STOCK_STATUS} value={item.stockStatus} />}</span>
                                    </button>
                                </li>
                            ))}
                            {visible.length === 0 && <li className="rounded-xl bg-slate-50 px-3 py-4 text-sm text-slate-500 sm:col-span-2">No items match.</li>}
                        </ul>
                    </section>
                </div>

                <div className="grid content-start gap-6">
                    <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                        <h2 className="font-semibold text-slate-900">Basket</h2>
                        {form.errors.items && <p role="alert" className="mt-3 rounded-xl bg-rose-50 px-3 py-2 text-sm text-rose-700">{form.errors.items}</p>}
                        {lines.length === 0 ? <p className="mt-4 rounded-xl bg-slate-50 px-3 py-4 text-sm text-slate-500">Nothing added yet. Choose items on the left.</p> : (
                            <ul className="mt-4 divide-y divide-slate-100">
                                {lines.map((line) => (
                                    <li key={line.item_id} className="flex flex-wrap items-center justify-between gap-3 py-3">
                                        <span className="min-w-0"><span className="block text-sm font-medium text-slate-900">{line.item.name}</span><span className="block text-xs text-slate-500">{money(line.item.sellingPrice)} each{Number(line.quantity) > line.item.quantity && <span className="ml-2 font-semibold text-rose-700">Only {line.item.quantity} in stock</span>}</span></span>
                                        <span className="flex items-center gap-3">
                                            <input type="number" min="1" value={line.quantity} onChange={(event) => setQuantity(line.item_id, event.target.value)} className="w-20 rounded-lg border border-slate-200 px-2 py-2 text-sm" aria-label={`Quantity of ${line.item.name}`} />
                                            <span className="w-24 text-right text-sm font-semibold text-slate-900">{money(line.item.sellingPrice * Number(line.quantity || 0))}</span>
                                            <button type="button" onClick={() => form.setData('items', form.data.items.filter((entry) => entry.item_id !== line.item_id))} className="text-xs font-semibold text-rose-700 hover:text-rose-900" aria-label={`Remove ${line.item.name}`}>Remove</button>
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}

                        {policy.canOverride && shortages.length > 0 && (
                            <label className="mt-4 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900"><input type="checkbox" checked={form.data.override_stock} onChange={(event) => form.setData('override_stock', event.target.checked)} className="mt-0.5 size-4 rounded border-amber-400" /><span>Sell more than is in stock<span className="block text-xs">Stock stops at zero and the shortfall is recorded as owed to the student.</span></span></label>
                        )}
                        {form.errors.override_stock && <p role="alert" className="mt-2 text-sm text-rose-700">{form.errors.override_stock}</p>}

                        {policy.canDiscount && lines.length > 0 && (
                            <div className="mt-5 grid grid-cols-2 gap-3">
                                <Field label="Discount" error={form.errors.discount_type}><select value={form.data.discount_type} onChange={(event) => form.setData('discount_type', event.target.value)} className={inputClass(form.errors.discount_type)}><option value="">None</option><option value="percent">Percent</option><option value="amount">Amount</option></select></Field>
                                <Field label={form.data.discount_type === 'percent' ? 'Percent off' : 'Amount off'} error={form.errors.discount_value}><input type="number" min="0" step="0.01" disabled={!form.data.discount_type} value={form.data.discount_value} onChange={(event) => form.setData('discount_value', event.target.value)} className={inputClass(form.errors.discount_value)} /></Field>
                            </div>
                        )}

                        <dl className="mt-5 space-y-1.5 border-t border-slate-100 pt-4 text-sm">
                            <div className="flex justify-between text-slate-600"><dt>Subtotal</dt><dd>{money(subtotal)}</dd></div>
                            {discount > 0 && <div className="flex justify-between text-slate-600"><dt>Discount</dt><dd>− {money(discount)}</dd></div>}
                            <div className="flex justify-between text-base font-semibold text-slate-900"><dt>Total</dt><dd>{money(total)}</dd></div>
                        </dl>
                    </section>

                    <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                        <h2 className="font-semibold text-slate-900">Payment</h2>
                        <p className="mt-1 text-sm text-slate-500">{policy.allowPartial ? 'Take full or part payment now. Leave the amount empty to record the sale as unpaid.' : 'The full amount is paid at the time of sale.'}</p>
                        <div className="mt-4 grid gap-3 sm:grid-cols-2">
                            <Field label="Amount received" error={form.errors['payment.amount']}>
                                <div className="flex gap-2">
                                    <input type="number" min="0" step="0.01" value={policy.allowPartial ? form.data.payment.amount : total.toFixed(2)} disabled={!policy.allowPartial} onChange={(event) => setPayment('amount', event.target.value)} className={inputClass(form.errors['payment.amount'])} />
                                    {policy.allowPartial && <button type="button" onClick={() => setPayment('amount', total.toFixed(2))} className="shrink-0 rounded-xl border border-slate-200 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">Full</button>}
                                </div>
                            </Field>
                            <Field label="Method" error={form.errors['payment.payment_method']}><select value={form.data.payment.payment_method} onChange={(event) => setPayment('payment_method', event.target.value)} className={inputClass(form.errors['payment.payment_method'])}>{paymentMethods.map((method) => <option key={method.key} value={method.key}>{method.label}</option>)}</select></Field>
                            <div className="sm:col-span-2"><Field label="Transaction reference (e.g. mobile money ID)" error={form.errors['payment.transaction_reference']}><input value={form.data.payment.transaction_reference} onChange={(event) => setPayment('transaction_reference', event.target.value)} className={inputClass(form.errors['payment.transaction_reference'])} maxLength={100} /></Field></div>
                            <div className="sm:col-span-2"><Field label="Notes" error={form.errors.notes}><input value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} className={inputClass(form.errors.notes)} maxLength={1000} /></Field></div>
                        </div>
                        {lines.length > 0 && <p className="mt-4 text-sm text-slate-600">Balance after this sale: <span className="font-semibold text-slate-900">{money(Math.max(0, total - paying))}</span></p>}
                    </section>

                    <div className="flex flex-wrap gap-3">
                        <button disabled={form.processing || !student || lines.length === 0 || blocked || !policy.hasActiveYear} className="rounded-xl bg-teal-800 px-6 py-3.5 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-50">{form.processing ? 'Saving…' : `Complete sale · ${money(total)}`}</button>
                        <Link href="/supplies/sales" className="rounded-xl border border-slate-200 px-5 py-3.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</Link>
                    </div>
                    {blocked && <p className="text-sm text-rose-700">Reduce the quantities that are above stock to continue.</p>}
                </div>
            </form>
        </AppLayout>
    );
}