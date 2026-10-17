import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import PageAlerts from '../../Components/Exams/PageAlerts';
import FeesNav from '../../Components/Fees/FeesNav';
import SpreadsheetImport from '../../Components/Fees/SpreadsheetImport';

export default function FeeHeadsIndex({ feeHeads, search, can }) {
    const [query, setQuery] = useState(search || '');

    function submitSearch(event) {
        event.preventDefault();
        router.get('/fee-heads', query.trim() ? { search: query.trim() } : {}, { preserveState: true, replace: true });
    }

    function deleteHead(head) {
        if (window.confirm(`Delete the fee head ${head.name}?`)) {
            router.delete(`/fee-heads/${head.id}`, { preserveScroll: true });
        }
    }

    return (
        <AppLayout title="Fee heads" description="Step 1: the kinds of charge the school makes.">
            <Head title="Fee heads" />
            <PageAlerts />
            <FeesNav current="heads" />

            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div className="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <p className="text-sm text-slate-500">A fee head such as Tuition is priced per class in the next step. {feeHeads.length} fee head{feeHeads.length === 1 ? '' : 's'}.</p>
                    <div className="flex flex-wrap items-center gap-3">
                        <form onSubmit={submitSearch} className="flex gap-2">
                            <input type="search" aria-label="Search fee heads" value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Code or name" className="w-44 rounded-xl border border-slate-200 px-3 py-2 text-sm" />
                            <button className="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Search</button>
                        </form>
                        {can.export && <a href="/fee-heads/export" className="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Export</a>}
                        {can.create && <Link href="/fee-heads/create" className="rounded-xl bg-teal-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-900">Add fee head</Link>}
                    </div>
                </div>

                {feeHeads.length === 0 ? (
                    <p className="px-6 py-12 text-center text-sm text-slate-500">{search ? `No fee heads match “${search}”.` : 'No fee heads yet. Add the first one to begin.'}</p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[640px] text-left text-sm">
                            <thead className="bg-slate-50 text-xs text-slate-500">
                                <tr><th className="px-5 py-3">Fee head</th><th className="px-5 py-3">Status</th><th className="px-5 py-3">Used in structures</th><th className="px-5 py-3"><span className="sr-only">Actions</span></th></tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {feeHeads.map((head) => (
                                    <tr key={head.id}>
                                        <td className="px-5 py-3.5">
                                            <Link href={`/fee-heads/${head.id}`} className="font-medium text-teal-800 hover:text-teal-950">{head.name}</Link>
                                            <p className="mt-0.5 text-xs text-slate-500">{head.code}</p>
                                        </td>
                                        <td className="px-5 py-3.5"><span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${head.isActive ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-600'}`}>{head.isActive ? 'Active' : 'Inactive'}</span></td>
                                        <td className="px-5 py-3.5 text-slate-600">{head.structuresCount}</td>
                                        <td className="px-5 py-3.5">
                                            <div className="flex items-center justify-end gap-3 text-xs font-semibold">
                                                <Link href={`/fee-heads/${head.id}`} className="text-teal-800 hover:text-teal-950">View</Link>
                                                {can.update && <Link href={`/fee-heads/${head.id}/edit`} className="text-teal-800 hover:text-teal-950">Edit</Link>}
                                                {can.delete && head.structuresCount === 0 && <button type="button" onClick={() => deleteHead(head)} className="text-rose-700 hover:text-rose-900">Delete</button>}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>

            {can.import && (
                <section className="mt-6">
                    <SpreadsheetImport action="/fee-heads/import" label="Import fee heads" description="Use a single-sheet CSV or XLSX file. Existing fee-head codes are updated; new codes are added." />
                </section>
            )}
        </AppLayout>
    );
}