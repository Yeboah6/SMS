import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import { fmtDateTime, useMoney } from '../../../lib/supplies';

export default function SalesReceipt({ sale, schoolName }) {
    const money = useMoney();

    return (
        <>
            <style>{`
                @media print {
                    @page { margin: 0; }
                    body * {
                        visibility: hidden;
                    }
                    .print-area, .print-area * {
                        visibility: visible;
                    }
                    .print-area {
                        position: absolute;
                        left: 0;
                        top: 0;
                        width: 100%;
                        margin: 0;
                        padding: 30px 30px 20px;
                        border: none !important;
                        box-shadow: none !important;
                    }
                    .receipt-table-wrap {
                        overflow: visible !important;
                    }
                }
            `}</style>

            <AppLayout title={`Receipt ${sale.saleNumber}`} description={`${sale.student.name} · ${sale.student.admissionNumber}`}>
                <Head title={`Receipt ${sale.saleNumber}`} />
                
                <div className="mb-5 flex flex-wrap items-center justify-between gap-3 print:hidden">
                    <Link href={`/supplies/sales/${sale.id}`} className="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Back to sale</Link>
                    <button type="button" onClick={() => window.print()} className="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-800">Print / Save as PDF</button>
                </div>

                <article className="print-area relative mx-auto max-w-2xl overflow-hidden border border-slate-300 bg-white p-8 sm:p-10 print:max-w-none print:border-0 print:p-8">
                    {sale.status === 'cancelled' && <div aria-hidden="true" className="pointer-events-none absolute inset-0 flex items-center justify-center"><span className="-rotate-12 select-none rounded-xl border-8 border-rose-600/40 px-8 py-3 text-6xl font-black tracking-widest text-rose-600/40">CANCELLED</span></div>}
                    <p className="text-center text-xs font-semibold uppercase tracking-[0.25em] text-teal-800">Sales receipt</p>
                    <h1 className="mt-3 text-center text-2xl font-bold tracking-tight text-slate-950">{schoolName}</h1>
                    <p className="mt-1 text-center text-sm text-slate-500">Books and school supplies</p>
                    <div className="my-6 h-px bg-slate-200" />

                    <dl className="grid gap-3 text-sm sm:grid-cols-2">
                        <Row label="Receipt no." value={sale.saleNumber} />
                        <Row label="Date" value={fmtDateTime(sale.soldAt)} />
                        <Row label="Student" value={sale.student.name} />
                        <Row label="Admission no." value={sale.student.admissionNumber} />
                        <Row label="Class" value={sale.className} />
                        <Row label="Served by" value={sale.soldBy} />
                    </dl>

                    <div className="receipt-table-wrap mt-6 max-w-full overflow-x-auto">
                        <table className="w-full min-w-[28rem] text-left text-sm">
                            <thead className="border-y border-slate-200 text-xs text-slate-500">
                                <tr>
                                    <th className="py-2 pr-2">Item</th>
                                    <th className="py-2 pr-2 text-right">Qty</th>
                                    <th className="py-2 pr-2 text-right">Price</th>
                                    <th className="py-2 text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {sale.items.map((line) => (
                                    <tr key={line.id}>
                                        <td className="py-2 pr-2 text-slate-800">
                                            {line.name}
                                            {line.returned > 0 && <span className="block text-xs text-slate-500">{line.returned} returned</span>}
                                        </td>
                                        <td className="py-2 pr-2 text-right">{line.quantity}</td>
                                        <td className="py-2 pr-2 text-right">{money(line.unitPrice)}</td>
                                        <td className="py-2 text-right text-slate-900">{money(line.lineTotal)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <dl className="mt-4 ml-auto w-full max-w-xs space-y-1.5 text-sm">
                        <div className="flex justify-between text-slate-600"><dt>Subtotal</dt><dd>{money(sale.subtotal)}</dd></div>
                        {sale.discount > 0 && <div className="flex justify-between text-slate-600"><dt>Discount</dt><dd>− {money(sale.discount)}</dd></div>}
                        {sale.returned > 0 && <div className="flex justify-between text-slate-600"><dt>Returned</dt><dd>− {money(sale.returned)}</dd></div>}
                        <div className="flex justify-between border-t border-slate-200 pt-1.5 text-base font-semibold text-slate-900"><dt>Total due</dt><dd>{money(sale.netDue)}</dd></div>
                        <div className="flex justify-between text-slate-600"><dt>Paid</dt><dd>{money(sale.netPaid)}</dd></div>
                        <div className="flex justify-between font-semibold text-slate-900"><dt>Balance</dt><dd>{money(sale.balance)}</dd></div>
                    </dl>

                    {sale.payments.length > 0 && (
                        <div className="mt-6">
                            <h2 className="text-xs font-semibold uppercase tracking-wide text-slate-500">Payments</h2>
                            <ul className="mt-2 divide-y divide-slate-100 text-sm">
                                {sale.payments.map((payment) => (
                                    <li key={payment.id} className="flex justify-between gap-3 py-1.5">
                                        <span className="text-slate-700">
                                            {payment.receiptNumber} · {payment.type === 'refund' ? 'Refund' : 'Payment'} · {payment.method}{payment.reference ? ` · ${payment.reference}` : ''}
                                        </span>
                                        <span className="text-slate-900">{payment.type === 'refund' ? '−' : ''}{money(payment.amount)}</span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                    <p className="mt-8 text-center text-xs text-slate-500">Thank you. Please keep this receipt.</p>
                </article>
            </AppLayout>
        </>
    );
}

function Row({ label, value }) {
    return <div><dt className="text-xs font-medium text-slate-500">{label}</dt><dd className="mt-0.5 text-slate-900">{value || '—'}</dd></div>;
}