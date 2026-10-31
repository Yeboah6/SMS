import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../../Layouts/AppLayout';
import PageAlerts from '../../../Components/Exams/PageAlerts';
import SuppliesNav from '../../../Components/Supplies/SuppliesNav';
import Pill from '../../../Components/Supplies/Pill';
import { Field, inputClass } from '../../Auth/Login';
import { PAYMENT_STATUS, SALE_STATUS, fmtDateTime, useMoney } from '../../../lib/supplies';

export default function SalesShow({ sale, paymentMethods, policy, idempotencyKey, today, can }) {
    const money = useMoney();
    const [panel, setPanel] = useState(null);
    const open = (name) => setPanel(panel === name ? null : name);

    return (
        <AppLayout title={sale.saleNumber} description={`${sale.student.name} · ${fmtDateTime(sale.soldAt)}`}>
            <Head title={sale.saleNumber} />
            <PageAlerts />
            <SuppliesNav current="sales" />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <Link href="/supplies/sales" className="text-sm font-semibold text-teal-800 hover:text-teal-950">Back to sales</Link>
                <div className="flex flex-wrap items-center gap-3 text-sm font-semibold">
                    <Link href={`/supplies/sales/${sale.id}/receipt`} className="rounded-lg border border-slate-200 px-3 py-2 text-slate-700 hover:bg-slate-50">Print receipt</Link>
                    {can.pay && <button type="button" onClick={() => open('pay')} className="rounded-lg bg-teal-800 px-3 py-2 text-white hover:bg-teal-900">Record payment</button>}
                    {can.refund && <button type="button" onClick={() => open('return')} className="rounded-lg border border-slate-200 px-3 py-2 text-slate-700 hover:bg-slate-50">Return items</button>}
                    {can.cancel && <button type="button" onClick={() => open('cancel')} className="rounded-lg border border-rose-200 px-3 py-2 text-rose-700 hover:bg-rose-50">Cancel sale</button>}
                </div>
            </div>

            <section className="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <Card label="Amount due" value={money(sale.netDue)} hint={sale.returned > 0 ? `${money(sale.total)} less ${money(sale.returned)} returned` : sale.discount > 0 ? `${money(sale.subtotal)} less ${money(sale.discount)} discount` : null} />
                <Card label="Paid" value={money(sale.netPaid)} hint={sale.refunded > 0 ? `${money(sale.paid)} received, ${money(sale.refunded)} refunded` : null} />
                <Card label="Balance" value={money(sale.balance)} tone={sale.balance > 0 ? 'text-rose-700' : ''} />
                <article className="rounded-2xl border border-slate-200 bg-white p-5"><p className="text-sm text-slate-500">Status</p><div className="mt-2 flex flex-wrap gap-2"><Pill map={SALE_STATUS} value={sale.status} /><Pill map={PAYMENT_STATUS} value={sale.paymentStatus} /></div><p className="mt-3 text-xs text-slate-500">Sold by {sale.soldBy}{sale.className ? ` · ${sale.className}` : ''}</p></article>
            </section>

            {sale.status === 'cancelled' && <p role="status" className="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">Cancelled {fmtDateTime(sale.cancelledAt)} by {sale.cancelledBy}: {sale.cancellationReason}. Stock was restored and any money kept was refunded. This record is kept for the books.</p>}

            {panel === 'pay' && <PaymentPanel sale={sale} paymentMethods={paymentMethods} policy={policy} idempotencyKey={idempotencyKey} today={today} onDone={() => setPanel(null)} />}
            {panel === 'return' && <ReturnPanel sale={sale} paymentMethods={paymentMethods} onDone={() => setPanel(null)} />}
            {panel === 'cancel' && <CancelPanel sale={sale} paymentMethods={paymentMethods} onDone={() => setPanel(null)} />}

            <div className="grid gap-6 xl:grid-cols-2">
                <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <h2 className="font-semibold text-slate-900">Items</h2>
                    <ul className="mt-4 divide-y divide-slate-100 text-sm">
                        {sale.items.map((line) => (
                            <li key={line.id} className="py-3">
                                <div className="flex justify-between gap-3"><span className="text-slate-800">{line.name}<span className="block text-xs text-slate-500">{line.quantity} × {money(line.unitPrice)}{line.returned > 0 ? ` · ${line.returned} returned` : ''}</span></span><span className="font-medium text-slate-900">{money(line.lineTotal)}</span></div>
                                {line.backordered > 0 && (
                                    <div className="mt-2 flex flex-wrap items-center justify-between gap-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900"><span>{line.backordered} unit{line.backordered === 1 ? '' : 's'} owed to the student (sold beyond stock)</span>{can.deliver && <button type="button" onClick={() => window.confirm('Hand over the backordered units now? Stock must be available.') && router.post(`/supplies/sales/${sale.id}/items/${line.id}/deliver`, { quantity: line.backordered }, { preserveScroll: true })} className="font-semibold underline">Hand over</button>}</div>
                                )}
                            </li>
                        ))}
                        <li className="flex justify-between gap-3 py-3 font-semibold text-slate-900"><span>Total</span><span>{money(sale.total)}</span></li>
                    </ul>
                    {sale.notes && <p className="mt-3 rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-600">{sale.notes}</p>}
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <h2 className="font-semibold text-slate-900">Payments and refunds</h2>
                    {sale.payments.length === 0 ? <p className="mt-4 rounded-xl bg-slate-50 px-3 py-4 text-sm text-slate-500">Nothing paid yet.</p> : (
                        <ul className="mt-4 divide-y divide-slate-100 text-sm">
                            {sale.payments.map((payment) => (
                                <li key={payment.id} className="flex flex-wrap items-start justify-between gap-3 py-3">
                                    <span><span className="font-medium text-slate-900">{payment.receiptNumber}</span><span className={`ml-2 rounded-full px-2 py-0.5 text-[11px] font-semibold ${payment.type === 'refund' ? 'bg-rose-50 text-rose-800' : 'bg-emerald-50 text-emerald-800'}`}>{payment.type === 'refund' ? 'Refund' : 'Payment'}</span><span className="block text-xs text-slate-500">{fmtDateTime(payment.paidAt)} · {payment.method}{payment.reference ? ` · ${payment.reference}` : ''} · {payment.by}</span>{payment.notes && <span className="block text-xs text-slate-500">{payment.notes}</span>}</span>
                                    <span className={`font-semibold ${payment.type === 'refund' ? 'text-rose-700' : 'text-slate-900'}`}>{payment.type === 'refund' ? '−' : ''}{money(payment.amount)}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                    <p className="mt-4 text-xs text-slate-500">Payments cannot be edited or deleted. A mistake is corrected with a refund, so the record always matches the cash.</p>
                </section>
            </div>
        </AppLayout>
    );
}

function Card({ label, value, hint = null, tone = '' }) {
    return <article className="rounded-2xl border border-slate-200 bg-white p-5"><p className="text-sm text-slate-500">{label}</p><p className={`mt-2 text-2xl font-semibold tracking-tight ${tone || 'text-slate-900'}`}>{value}</p>{hint && <p className="mt-1 text-xs text-slate-500">{hint}</p>}</article>;
}

function Panel({ title, children }) {
    return <section className="mb-6 rounded-2xl border border-teal-200 bg-white p-5 sm:p-6"><h2 className="font-semibold text-slate-900">{title}</h2><div className="mt-4">{children}</div></section>;
}

function MethodSelect({ form, methods, field, label }) {
    return <Field label={label} error={form.errors[field]}><select value={form.data[field]} onChange={(event) => form.setData(field, event.target.value)} className={inputClass(form.errors[field])}><option value="">Select method</option>{methods.map((method) => <option key={method.key} value={method.key}>{method.label}</option>)}</select></Field>;
}

function PaymentPanel({ sale, paymentMethods, policy, idempotencyKey, today, onDone }) {
    const money = useMoney();
    const form = useForm({ amount: sale.balance.toFixed(2), payment_method: paymentMethods[0]?.key || 'cash', transaction_reference: '', notes: '', idempotency_key: idempotencyKey });

    return (
        <Panel title="Record a payment">
            <form onSubmit={(event) => { event.preventDefault(); form.post(`/supplies/sales/${sale.id}/payments`, { preserveScroll: true, onSuccess: onDone }); }} className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <Field label={`Amount (balance ${money(sale.balance)})`} error={form.errors.amount}><input type="number" min="0.01" max={sale.balance} step="0.01" value={form.data.amount} disabled={!policy.allowPartial} onChange={(event) => form.setData('amount', event.target.value)} className={inputClass(form.errors.amount)} required /></Field>
                <MethodSelect form={form} methods={paymentMethods} field="payment_method" label="Method" />
                <Field label="Transaction reference" error={form.errors.transaction_reference}><input value={form.data.transaction_reference} onChange={(event) => form.setData('transaction_reference', event.target.value)} className={inputClass(form.errors.transaction_reference)} maxLength={100} /></Field>
                <Field label="Notes" error={form.errors.notes}><input value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} className={inputClass(form.errors.notes)} maxLength={1000} /></Field>
                {form.errors.idempotency_key && <p role="alert" className="text-sm text-rose-700 sm:col-span-2 xl:col-span-4">{form.errors.idempotency_key}</p>}
                <div className="flex flex-wrap items-center gap-3 sm:col-span-2 xl:col-span-4"><button disabled={form.processing} className="rounded-xl bg-teal-800 px-5 py-3 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-60">{form.processing ? 'Saving…' : 'Record payment'}</button>{!policy.allowPartial && <span className="text-xs text-slate-500">Partial payments are not allowed: pay the whole balance.</span>}</div>
            </form>
        </Panel>
    );
}

function ReturnPanel({ sale, paymentMethods, onDone }) {
    const money = useMoney();
    const returnable = sale.items.filter((line) => line.kept > 0);
    const form = useForm({ items: returnable.map((line) => ({ sale_item_id: line.id, quantity: 0 })), reason: '', restock: true, refund_method: '', refund_reference: '' });
    const ratio = sale.subtotal > 0 ? (sale.subtotal - sale.discount) / sale.subtotal : 0;
    const value = form.data.items.reduce((sum, row) => sum + (returnable.find((line) => line.id === row.sale_item_id)?.unitPrice || 0) * Number(row.quantity || 0) * ratio, 0);
    const mustRefund = sale.netPaid - (sale.netDue - value) > 0.004;

    function setQuantity(index, value) {
        form.setData('items', form.data.items.map((row, position) => (position === index ? { ...row, quantity: value } : row)));
    }

    return (
        <Panel title="Return items">
            <form onSubmit={(event) => { event.preventDefault(); form.post(`/supplies/sales/${sale.id}/return`, { preserveScroll: true, onSuccess: onDone }); }} className="grid gap-4">
                {form.errors.items && <p role="alert" className="rounded-xl bg-rose-50 px-3 py-2 text-sm text-rose-700">{form.errors.items}</p>}
                <ul className="divide-y divide-slate-100 rounded-xl border border-slate-100 text-sm">
                    {returnable.map((line, index) => (
                        <li key={line.id} className="flex flex-wrap items-center justify-between gap-3 px-4 py-3"><span className="text-slate-800">{line.name}<span className="block text-xs text-slate-500">{line.kept} with the student at {money(line.unitPrice)}</span></span><input type="number" min="0" max={line.kept} value={form.data.items[index].quantity} onChange={(event) => setQuantity(index, event.target.value)} className="w-24 rounded-lg border border-slate-200 px-2 py-2 text-sm" aria-label={`Quantity of ${line.name} to return`} /></li>
                    ))}
                </ul>
                <p className="text-sm text-slate-600">Value of goods returned: <span className="font-semibold text-slate-900">{money(value)}</span>{ratio < 1 && ratio > 0 && <span className="text-xs text-slate-500"> (after this sale's discount)</span>}</p>
                <Field label="Reason" error={form.errors.reason}><textarea rows={2} value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} className={inputClass(form.errors.reason)} minLength={5} required /></Field>
                <label className="flex items-center gap-3 text-sm text-slate-700"><input type="checkbox" checked={form.data.restock} onChange={(event) => form.setData('restock', event.target.checked)} className="size-4 rounded border-slate-300 text-teal-700" />Put the items back in stock (untick if they are damaged)</label>
                {mustRefund && (
                    <div className="grid gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 sm:grid-cols-2"><p className="text-sm text-amber-900 sm:col-span-2">The student has paid more than they now owe. The difference is refunded now.</p><MethodSelect form={form} methods={paymentMethods} field="refund_method" label="Refund paid by" /><Field label="Refund reference" error={form.errors.refund_reference}><input value={form.data.refund_reference} onChange={(event) => form.setData('refund_reference', event.target.value)} className={inputClass(form.errors.refund_reference)} maxLength={100} /></Field></div>
                )}
                <div><button disabled={form.processing || value <= 0} className="rounded-xl bg-teal-800 px-5 py-3 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-60">{form.processing ? 'Saving…' : 'Record return'}</button></div>
            </form>
        </Panel>
    );
}

function CancelPanel({ sale, paymentMethods, onDone }) {
    const money = useMoney();
    const form = useForm({ reason: '', refund_method: '', refund_reference: '' });

    return (
        <Panel title="Cancel this sale">
            <form onSubmit={(event) => { event.preventDefault(); if (window.confirm(`Cancel ${sale.saleNumber}? All items go back on the shelf and any money paid is refunded.`)) form.post(`/supplies/sales/${sale.id}/cancel`, { preserveScroll: true, onSuccess: onDone }); }} className="grid gap-4">
                <p className="text-sm text-slate-600">The sale is kept for the books but counts as zero. Everything still with the student is restocked.{sale.netPaid > 0 ? ` ${money(sale.netPaid)} has been paid and must be refunded.` : ''}</p>
                <Field label="Reason" error={form.errors.reason}><textarea rows={2} value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} className={inputClass(form.errors.reason)} minLength={5} required /></Field>
                {sale.netPaid > 0 && <div className="grid gap-3 sm:grid-cols-2"><MethodSelect form={form} methods={paymentMethods} field="refund_method" label="Refund paid by" /><Field label="Refund reference" error={form.errors.refund_reference}><input value={form.data.refund_reference} onChange={(event) => form.setData('refund_reference', event.target.value)} className={inputClass(form.errors.refund_reference)} maxLength={100} /></Field></div>}
                <div><button disabled={form.processing} className="rounded-xl bg-rose-700 px-5 py-3 text-sm font-semibold text-white hover:bg-rose-800 disabled:opacity-60">{form.processing ? 'Cancelling…' : 'Cancel sale'}</button></div>
            </form>
        </Panel>
    );
}