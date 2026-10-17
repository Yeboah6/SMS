import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import PageAlerts from '../../Components/Exams/PageAlerts';
import FeesNav from '../../Components/Fees/FeesNav';
import { dateLabel, useMoney } from '../../lib/fees';

export default function FeeHeadsShow({ feeHead, structures, can }) {
    const money = useMoney();

    function deleteHead() {
        if (window.confirm(`Delete the fee head ${feeHead.name}?`)) {
            router.delete(`/fee-heads/${feeHead.id}`);
        }
    }

    return (
        <AppLayout title={feeHead.name} description={feeHead.code}>
            <Head title={feeHead.name} />
            <PageAlerts />
            <FeesNav current="heads" />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <Link href="/fee-heads" className="text-sm font-semibold text-teal-800 hover:text-teal-950">Back to fee heads</Link>
                <div className="flex items-center gap-4 text-sm font-semibold">
                    {can.update && <Link href={`/fee-heads/${feeHead.id}/edit`} className="text-teal-800 hover:text-teal-950">Edit fee head</Link>}
                    {can.delete && structures.length === 0 && <button type="button" onClick={deleteHead} className="text-rose-700 hover:text-rose-900">Delete</button>}
                </div>
            </div>

            <section className="mb-6 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <div className="flex items-center justify-between gap-3">
                    <h2 className="font-semibold text-slate-900">Details</h2>
                    <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${feeHead.isActive ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-600'}`}>{feeHead.isActive ? 'Active' : 'Inactive'}</span>
                </div>
                <dl className="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                    <div><dt className="text-xs font-medium text-slate-500">Code</dt><dd className="mt-1 text-slate-900">{feeHead.code}</dd></div>
                    <div><dt className="text-xs font-medium text-slate-500">Name</dt><dd className="mt-1 text-slate-900">{feeHead.name}</dd></div>
                    <div className="sm:col-span-2"><dt className="text-xs font-medium text-slate-500">Description</dt><dd className="mt-1 text-slate-900">{feeHead.description || '—'}</dd></div>
                </dl>
                {structures.length > 0 && <p className="mt-4 text-sm text-slate-500">This fee head is used in fee structures, so it cannot be deleted. Deactivate it to stop using it in new structures.</p>}
            </section>

            <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <h2 className="font-semibold text-slate-900">Where it is used</h2>
                {structures.length === 0 ? (
                    <p className="mt-4 rounded-xl bg-slate-50 px-3 py-4 text-sm text-slate-500">Not used in any fee structure yet.</p>
                ) : (
                    <div className="mt-4 overflow-x-auto">
                        <table className="w-full min-w-[480px] text-left text-sm">
                            <thead className="border-b border-slate-100 text-xs text-slate-500"><tr><th className="py-3 pr-4">Year</th><th className="py-3 pr-4">Class</th><th className="py-3 pr-4">Amount</th><th className="py-3">Due</th></tr></thead>
                            <tbody className="divide-y divide-slate-100">
                                {structures.map((structure) => (
                                    <tr key={structure.id}>
                                        <td className="py-3 pr-4"><Link href={`/fee-structures?academic_year_id=${structure.academicYearId}`} className="text-teal-800 hover:text-teal-950">{structure.academicYear}</Link></td>
                                        <td className="py-3 pr-4">{structure.className}</td>
                                        <td className="py-3 pr-4">{money(structure.amount)}</td>
                                        <td className="py-3">{dateLabel(structure.dueDate)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>
        </AppLayout>
    );
}