import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import PageAlerts from '../../Components/Exams/PageAlerts';

const PROFILE_TYPES = { staff: 'Staff', student: 'Student', guardian: 'Parent' };

export default function UsersIndex({ users, roles, filters }) {
    const [search, setSearch] = useState(filters.search || '');

    function visit(overrides = {}) {
        const params = { search, role: filters.role, ...overrides };
        Object.keys(params).forEach((key) => !params[key] && delete params[key]);
        router.get('/users', params, { preserveState: true, replace: true });
    }

    function deactivate(user) {
        if (window.confirm(`Deactivate the account for ${user.name}? They will no longer be able to sign in. Historical records are retained.`)) {
            router.delete(`/users/${user.id}`, { preserveScroll: true });
        }
    }

    return (
        <AppLayout title="Users" description="Manage existing sign-in accounts: roles, passwords and access.">
            <Head title="Users" />
            <PageAlerts />

            <p className="mb-6 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600">
                Accounts are created where the person is added: staff on the <Link href="/staff" className="font-semibold text-teal-800 hover:text-teal-950">staff form</Link>, students when an admission is enrolled, and parents from a student's guardian invite.
            </p>

            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div className="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 className="font-semibold text-slate-900">School accounts</h2>
                        <p className="mt-1 text-sm text-slate-500">{users.total.toLocaleString()} account{users.total === 1 ? '' : 's'}</p>
                    </div>
                    <div className="flex flex-wrap items-center gap-3">
                        <select aria-label="Filter by role" value={filters.role} onChange={(event) => visit({ role: event.target.value })} className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                            <option value="">All roles</option>
                            {roles.map((role) => <option key={role} value={role}>{role}</option>)}
                        </select>
                        <form onSubmit={(event) => { event.preventDefault(); visit(); }} className="flex gap-2">
                            <input type="search" aria-label="Search users" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search name or email" className="w-56 rounded-xl border border-slate-200 px-3 py-2 text-sm" />
                            <button className="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Search</button>
                        </form>
                    </div>
                </div>

                {users.data.length === 0 ? (
                    <p className="px-6 py-14 text-center text-sm text-slate-500">No user accounts match.</p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[760px] text-left text-sm">
                            <thead className="bg-slate-50 text-xs text-slate-500">
                                <tr><th className="px-5 py-3">Account</th><th className="px-5 py-3">Role</th><th className="px-5 py-3">Linked profile</th><th className="px-5 py-3"><span className="sr-only">Actions</span></th></tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {users.data.map((user) => (
                                    <tr key={user.id} className="hover:bg-slate-50/70">
                                        <td className="px-5 py-3.5">
                                            <Link href={`/users/${user.id}`} className="font-medium text-teal-800 hover:text-teal-950">{user.name}</Link>
                                            <p className="mt-0.5 text-xs text-slate-500">{user.email}</p>
                                        </td>
                                        <td className="px-5 py-3.5">
                                            <div className="flex flex-wrap gap-1.5">
                                                {user.roles.length === 0 ? <span className="text-slate-400">No role</span> : user.roles.map((role) => <span key={role} className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">{role}</span>)}
                                            </div>
                                        </td>
                                        <td className="px-5 py-3.5 text-slate-600">
                                            {user.profile
                                                ? (user.profile.href
                                                    ? <Link href={user.profile.href} className="text-teal-800 hover:text-teal-950">{PROFILE_TYPES[user.profile.type]} · {user.profile.label.replace(/^(staff|student) /, '')}</Link>
                                                    : PROFILE_TYPES[user.profile.type])
                                                : <span className="text-slate-400">—</span>}
                                        </td>
                                        <td className="px-5 py-3.5">
                                            <div className="flex items-center justify-end gap-3 text-xs font-semibold">
                                                <Link href={`/users/${user.id}`} className="text-teal-800 hover:text-teal-950">View</Link>
                                                {user.canEdit && <Link href={`/users/${user.id}/edit`} className="text-teal-800 hover:text-teal-950">Edit</Link>}
                                                {user.canDeactivate && <button type="button" onClick={() => deactivate(user)} className="text-rose-700 hover:text-rose-900">Deactivate</button>}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {users.links.length > 3 && (
                    <nav aria-label="Pagination" className="flex flex-wrap justify-center gap-1 border-t border-slate-100 px-4 py-4">
                        {users.links.map((link, index) => (
                            <button key={`${link.label}-${index}`} type="button" disabled={!link.url} onClick={() => link.url && router.visit(link.url, { preserveScroll: true })} aria-current={link.active ? 'page' : undefined} className={`rounded-lg px-3 py-2 text-sm ${link.active ? 'bg-teal-800 font-semibold text-white' : 'text-slate-600 hover:bg-slate-100 disabled:text-slate-300'}`}>
                                {link.label.replace(/<[^>]*>/g, '').replaceAll('&laquo;', '‹').replaceAll('&raquo;', '›')}
                            </button>
                        ))}
                    </nav>
                )}
            </section>
        </AppLayout>
    );
}