import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import PageAlerts from '../../Components/Exams/PageAlerts';

export default function StaffIndex({ staff, filters, can }) {
    const [search, setSearch] = useState(filters.search || '');

    function submitSearch(event) {
        event.preventDefault();
        router.get('/staff', search.trim() ? { search: search.trim() } : {}, { preserveState: true, replace: true });
    }

    function deleteStaff(member) {
        if (window.confirm(`Delete ${member.name}? This also removes their sign-in account. Staff with teaching assignments cannot be deleted.`)) {
            router.delete(`/staff/${member.id}`, { preserveScroll: true });
        }
    }

    return (
        <AppLayout title="Staff" description="Manage staff profiles, roles and sign-in accounts.">
            <Head title="Staff" />
            <PageAlerts />

            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div className="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 className="font-semibold text-slate-900">Staff directory</h2>
                        <p className="mt-1 text-sm text-slate-500">{staff.total.toLocaleString()} staff member{staff.total === 1 ? '' : 's'}</p>
                    </div>
                    <div className="flex flex-wrap items-center gap-3">
                        <form onSubmit={submitSearch} className="flex gap-2">
                            <input type="search" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Name, number or email" aria-label="Search staff" className="w-52 rounded-xl border border-slate-200 px-3 py-2 text-sm" />
                            <button className="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Search</button>
                        </form>
                        {can.manageDepartments && <Link href="/departments" className="text-sm font-semibold text-teal-800 hover:text-teal-950">Departments</Link>}
                        {can.manageDesignations && <Link href="/designations" className="text-sm font-semibold text-teal-800 hover:text-teal-950">Designations</Link>}
                        {can.create && <Link href="/staff/create" className="rounded-xl bg-teal-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-900">Add staff</Link>}
                    </div>
                </div>

                {staff.data.length === 0 ? (
                    <p className="px-6 py-12 text-center text-sm text-slate-500">{filters.search ? `No staff match “${filters.search}”.` : 'No staff profiles have been created.'}</p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[760px] text-left text-sm">
                            <thead className="bg-slate-50 text-xs text-slate-500">
                                <tr>
                                    <th className="px-5 py-3">Staff</th>
                                    <th className="px-5 py-3">Role</th>
                                    <th className="px-5 py-3">Department</th>
                                    <th className="px-5 py-3">Status</th>
                                    <th className="px-5 py-3"><span className="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {staff.data.map((member) => (
                                    <tr key={member.id}>
                                        <td className="px-5 py-3">
                                            <Link href={`/staff/${member.id}`} className="font-medium text-slate-900 hover:text-teal-800">{member.name}</Link>
                                            <p className="text-xs text-slate-500">{member.staffNumber}{member.email ? ` · ${member.email}` : ''}</p>
                                        </td>
                                        <td className="px-5 py-3 text-slate-700">{member.role || <span className="text-slate-400">No account</span>}</td>
                                        <td className="px-5 py-3 text-slate-600">
                                            {member.department || '—'}
                                            {member.designation && <p className="text-xs text-slate-500">{member.designation}</p>}
                                        </td>
                                        <td className="px-5 py-3">
                                            <span className={`rounded-full px-2.5 py-1 text-xs font-semibold capitalize ${member.status === 'active' ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-600'}`}>{member.status}</span>
                                        </td>
                                        <td className="px-5 py-3">
                                            <div className="flex items-center justify-end gap-3 text-xs font-semibold">
                                                <Link href={`/staff/${member.id}`} className="text-teal-800 hover:text-teal-950">View</Link>
                                                {can.update && <Link href={`/staff/${member.id}/edit`} className="text-teal-800 hover:text-teal-950">Edit</Link>}
                                                {can.delete && <button type="button" onClick={() => deleteStaff(member)} className="text-rose-700 hover:text-rose-900">Delete</button>}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {staff.last_page > 1 && (
                    <nav aria-label="Staff pages" className="flex flex-wrap gap-2 border-t border-slate-100 px-5 py-3">
                        {staff.links.map((link, index) => (
                            <Link
                                key={`${link.label}-${index}`}
                                href={link.url || '#'}
                                preserveScroll
                                className={`rounded-lg px-3 py-2 text-xs ${link.active ? 'bg-teal-700 text-white' : 'border border-slate-200 bg-white text-slate-700'} ${!link.url ? 'pointer-events-none opacity-50' : ''}`}
                            >
                                {link.label.replace('&laquo;', '‹').replace('&raquo;', '›')}
                            </Link>
                        ))}
                    </nav>
                )}
            </section>
        </AppLayout>
    );
}