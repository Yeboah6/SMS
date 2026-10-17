import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import PageAlerts from '../../Components/Exams/PageAlerts';
import { STATUS_TABS, statusClass, typeLabel } from '../../lib/certificates';

export default function CertificatesIndex({ requests, filters, certificateTypes, counts, can }) {
    const [search, setSearch] = useState(filters.search || '');
    const total = Object.values(counts).reduce((sum, value) => sum + Number(value), 0);

    function visit(params) {
        const clean = Object.fromEntries(Object.entries(params).filter(([, value]) => value));
        router.get('/certificates', clean, { preserveState: true, replace: true });
    }

    const current = (overrides = {}) => ({ search, status: filters.status, type: filters.type, ...overrides });

    function submitSearch(event) {
        event.preventDefault();
        visit(current());
    }

    function deleteRequest(item) {
        if (window.confirm(`Delete the ${typeLabel(item.certificateType)} certificate request for ${item.studentName}?`)) {
            router.delete(`/certificates/${item.id}`, { preserveScroll: true });
        }
    }

    const tabs = [['', 'All', total], ...STATUS_TABS.map(([value, label]) => [value, label, Number(counts[value] || 0)])];

    return (
        <AppLayout title="Certificates" description="Review requests, issue certificates, and revoke them when needed.">
            <Head title="Certificates" />
            <PageAlerts />

            <nav aria-label="Certificate status" className="mb-5 flex gap-2 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-2">
                {tabs.map(([value, label, count]) => (
                    <button
                        key={value || 'all'}
                        type="button"
                        aria-pressed={filters.status === value}
                        onClick={() => visit(current({ status: value }))}
                        className={`shrink-0 rounded-xl px-4 py-2.5 text-sm font-semibold ${filters.status === value ? 'bg-teal-800 text-white' : 'text-slate-600 hover:bg-slate-100'}`}
                    >
                        {label} <span className={filters.status === value ? 'text-teal-100' : 'text-slate-400'}>{count}</span>
                    </button>
                ))}
            </nav>

            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div className="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <h2 className="font-semibold text-slate-900">{requests.total.toLocaleString()} {requests.total === 1 ? 'certificate' : 'certificates'}</h2>
                    <div className="flex flex-wrap items-center gap-3">
                        <select aria-label="Filter by type" value={filters.type} onChange={(event) => visit(current({ type: event.target.value }))} className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800">
                            <option value="">All types</option>
                            {certificateTypes.map((type) => <option key={type} value={type}>{typeLabel(type)}</option>)}
                        </select>
                        <form onSubmit={submitSearch} className="flex gap-2">
                            <input type="search" aria-label="Search certificates" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Student, admission or certificate no." className="w-60 rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-teal-700 focus:outline-none" />
                            <button className="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Search</button>
                        </form>
                        {can.issue && <Link href="/certificates/create" className="rounded-xl bg-teal-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-900">Issue certificate</Link>}
                    </div>
                </div>

                {requests.data.length === 0 ? (
                    <div className="px-6 py-14 text-center">
                        <p className="font-medium text-slate-800">No certificates found</p>
                        <p className="mt-1 text-sm text-slate-500">Requests and issued certificates will appear here.</p>
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[820px] text-left text-sm">
                            <thead className="bg-slate-50 text-xs text-slate-500">
                                <tr>
                                    <th className="px-5 py-3">Student</th>
                                    <th className="px-5 py-3">Type</th>
                                    <th className="px-5 py-3">Status</th>
                                    <th className="px-5 py-3">Number</th>
                                    <th className="px-5 py-3">Requested</th>
                                    <th className="px-5 py-3"><span className="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {requests.data.map((item) => (
                                    <tr key={item.id} className="hover:bg-slate-50/70">
                                        <td className="px-5 py-3.5">
                                            <Link href={`/certificates/${item.id}`} className="font-medium text-teal-800 hover:text-teal-950">{item.studentName}</Link>
                                            <p className="mt-0.5 text-xs text-slate-500">{item.admissionNumber}</p>
                                        </td>
                                        <td className="px-5 py-3.5 text-slate-600">{typeLabel(item.certificateType)}</td>
                                        <td className="px-5 py-3.5"><span className={`rounded-full px-2.5 py-1 text-xs font-semibold capitalize ${statusClass(item.status)}`}>{item.status}</span></td>
                                        <td className="px-5 py-3.5 text-slate-600">{item.certificateNumber || '—'}</td>
                                        <td className="px-5 py-3.5 text-slate-600">{item.requestedAt}{item.requestedBy && <span className="block text-xs text-slate-500">{item.requestedBy}</span>}</td>
                                        <td className="px-5 py-3.5">
                                            <div className="flex items-center justify-end gap-3 text-xs font-semibold">
                                                <Link href={`/certificates/${item.id}`} className="text-teal-800 hover:text-teal-950">View</Link>
                                                {item.canEdit && <Link href={`/certificates/${item.id}/edit`} className="text-teal-800 hover:text-teal-950">Edit</Link>}
                                                {item.canDelete && <button type="button" onClick={() => deleteRequest(item)} className="text-rose-700 hover:text-rose-900">Delete</button>}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {requests.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-slate-100 px-5 py-3">
                        <button type="button" disabled={requests.current_page <= 1} onClick={() => visit({ ...current(), page: requests.current_page - 1 })} className="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 disabled:opacity-40">Previous</button>
                        <span className="text-xs text-slate-500">Page {requests.current_page} of {requests.last_page}</span>
                        <button type="button" disabled={requests.current_page >= requests.last_page} onClick={() => visit({ ...current(), page: requests.current_page + 1 })} className="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 disabled:opacity-40">Next</button>
                    </div>
                )}
            </section>
        </AppLayout>
    );
}