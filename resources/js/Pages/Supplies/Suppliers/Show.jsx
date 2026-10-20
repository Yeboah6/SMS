import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import PageAlerts from '../../../Components/Exams/PageAlerts';
import SuppliesNav from '../../../Components/Supplies/SuppliesNav';
import Pager from '../../../Components/Supplies/Pager';
import { fmtDate, useMoney } from '../../../../lib/supplies';

export default function SuppliersShow({ supplier, purchases, can }) {
    const money = useMoney();

    return (
        <AppLayout title={supplier.name} description={supplier.contactPerson || 'Supplier'}>
            <Head title={supplier.name} />
            <PageAlerts />
            <SuppliesNav current="suppliers" />
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3"><Link href="/supplies/suppliers" className="text-sm font-semibold text-teal-800 hover:text-teal-950">Back to suppliers</Link>{can.update && <Link href={`/supplies/suppliers/${supplier.id}/edit`} className="text-sm font-semibold text-teal-800 hover:text-teal-950">Edit supplier</Link>}</div>
            <section className="mb-6 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <dl className="grid gap-4 text-sm sm:grid-cols-2 xl:grid-cols-4">
                    {[['Contact', supplier.contactPerson], ['Phone', supplier.phone], ['Email', supplier.email], ['Status', supplier.isActive ? 'Active' : 'Inactive']].map(([label, value]) => <div key={label}><dt className="text-xs font-medium text-slate-500">{label}</dt><dd className="mt-1 text-slate-900">{value || '—'}</dd></div>)}
                    <div className="sm:col-span-2 xl:col-span-4"><dt className="text-xs font-medium text-slate-500">Address</dt><dd className="mt-1 text-slate-900">{supplier.address || '—'}</dd></div>
                    {supplier.notes && <div className="sm:col-span-2 xl:col-span-4"><dt className="text-xs font-medium text-slate-500">Notes</dt><dd className="mt-1 whitespace-pre-wrap text-slate-900">{supplier.notes}</dd></div>}
                </dl>
            </section>
            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div className="border-b border-slate-100 px-5 py-4"><h2 className="font-semibold text-slate-900">Purchases from this supplier</h2></div>
                {purchases.data.length === 0 ? <p className="px-6 py-12 text-center text-sm text-slate-500">No purchases yet.</p> : (
                    <ul className="divide-y divide-slate-100 text-sm">
                        {purchases.data.map((purchase) => <li key={purchase.id} className="flex flex-wrap items-center justify-between gap-3 px-5 py-3"><span><Link href={`/supplies/purchases/${purchase.id}`} className="font-medium text-teal-800 hover:text-teal-950">{purchase.number}</Link><span className="block text-xs text-slate-500">{fmtDate(purchase.date)}{purchase.reference ? ` · ${purchase.reference}` : ''}</span></span><span className="flex items-center gap-3"><span className="font-medium text-slate-900">{money(purchase.total)}</span><span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${purchase.status === 'received' ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-600'}`}>{purchase.status === 'received' ? 'Received' : 'Reversed'}</span></span></li>)}
                    </ul>
                )}
                <Pager links={purchases.links} />
            </section>
        </AppLayout>
    );
}