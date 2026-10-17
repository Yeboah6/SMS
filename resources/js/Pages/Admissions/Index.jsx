import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import PageAlerts from '../../Components/Exams/PageAlerts';
import { StatusBadge } from '../../Components/Admissions/AdmissionStage';
import { ALL_STATUSES } from '../../lib/admissions';

export default function AdmissionsIndex({ admissions, search, selectedStatus, counts, can }) {
    const [searchText, setSearchText] = useState(search);
    const total = Object.values(counts).reduce((sum, value) => sum + Number(value), 0);

    function visit(params) {
        router.get('/admissions', params, { preserveState: true, replace: true });
    }

    function applySearch(event) {
        event.preventDefault();
        visit({ search: searchText, status: selectedStatus });
    }

    function deleteAdmission(admission) {
        if (window.confirm(`Delete the record for ${admission.childName} (${admission.applicationNumber})? Its documents and follow-ups are deleted too.`)) {
            router.delete(`/admissions/${admission.id}`, { preserveScroll: true });
        }
    }

    const tabs = [['', 'All', total], ...ALL_STATUSES.map(([value, label]) => [value, label, Number(counts[value] || 0)])];

    return (
        <AppLayout title="Admissions" description="Track enquiries, applications, reviews, approvals, and enrolment.">
            <Head title="Admissions" />
            <PageAlerts />

            <nav aria-label="Admission stages" className="mb-5 flex gap-2 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-2">
                {tabs.map(([value, label, count]) => (
                    <button
                        key={value || 'all'}
                        type="button"
                        aria-pressed={selectedStatus === value}
                        onClick={() => visit({ search: searchText, status: value })}
                        className={`shrink-0 rounded-xl px-4 py-2.5 text-sm font-semibold ${selectedStatus === value ? 'bg-teal-800 text-white' : 'text-slate-600 hover:bg-slate-100'}`}
                    >
                        {label} <span className={selectedStatus === value ? 'text-teal-100' : 'text-slate-400'}>{count}</span>
                    </button>
                ))}
            </nav>

            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div className="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <h2 className="font-semibold text-slate-900">{admissions.total.toLocaleString()} {admissions.total === 1 ? 'application' : 'applications'}</h2>
                    <div className="flex flex-wrap items-center gap-3">
                        <form onSubmit={applySearch} className="flex gap-2">
                            <input type="search" aria-label="Search admissions" value={searchText} onChange={(event) => setSearchText(event.target.value)} placeholder="Application, child, or parent" className="w-56 rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-teal-700 focus:outline-none" />
                            <button className="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Search</button>
                        </form>
                        {can.create && <Link href="/admissions/create" className="rounded-xl bg-teal-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-900">New enquiry</Link>}
                    </div>
                </div>

                {admissions.data.length === 0 ? (
                    <div className="px-6 py-14 text-center">
                        <p className="font-medium text-slate-800">No admissions found</p>
                        <p className="mt-1 text-sm text-slate-500">New enquiries will appear here as they are recorded.</p>
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[820px] text-left text-sm">
                            <thead className="bg-slate-50 text-xs text-slate-500">
                                <tr>
                                    <th className="px-5 py-3">Child</th>
                                    <th className="px-5 py-3">Parent</th>
                                    <th className="px-5 py-3">Applying for</th>
                                    <th className="px-5 py-3">Stage</th>
                                    <th className="px-5 py-3">Received</th>
                                    <th className="px-5 py-3"><span className="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {admissions.data.map((admission) => (
                                    <tr key={admission.id} className="hover:bg-slate-50/70">
                                        <td className="px-5 py-3.5">
                                            <Link href={`/admissions/${admission.id}`} className="font-medium text-teal-800 hover:text-teal-950">{admission.childName}</Link>
                                            <p className="mt-0.5 text-xs text-slate-500">{admission.applicationNumber}</p>
                                        </td>
                                        <td className="px-5 py-3.5 text-slate-600">{admission.parentName}<span className="block text-xs text-slate-500">{admission.parentPhone}</span></td>
                                        <td className="px-5 py-3.5 text-slate-600">{admission.applyingFor}</td>
                                        <td className="px-5 py-3.5"><StatusBadge status={admission.status} /></td>
                                        <td className="px-5 py-3.5 text-slate-600">{admission.createdAt}</td>
                                        <td className="px-5 py-3.5">
                                            <div className="flex items-center justify-end gap-3 text-xs font-semibold">
                                                <Link href={`/admissions/${admission.id}`} className="text-teal-800 hover:text-teal-950">View</Link>
                                                {admission.canEdit && <Link href={`/admissions/${admission.id}/edit`} className="text-teal-800 hover:text-teal-950">Edit</Link>}
                                                {admission.canDelete && <button type="button" onClick={() => deleteAdmission(admission)} className="text-rose-700 hover:text-rose-900">Delete</button>}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {admissions.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-slate-100 px-5 py-3">
                        <button type="button" disabled={admissions.current_page <= 1} onClick={() => visit({ search: searchText, status: selectedStatus, page: admissions.current_page - 1 })} className="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 disabled:opacity-40">Previous</button>
                        <span className="text-xs text-slate-500">Page {admissions.current_page} of {admissions.last_page}</span>
                        <button type="button" disabled={admissions.current_page >= admissions.last_page} onClick={() => visit({ search: searchText, status: selectedStatus, page: admissions.current_page + 1 })} className="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 disabled:opacity-40">Next</button>
                    </div>
                )}
            </section>
        </AppLayout>
    );
}