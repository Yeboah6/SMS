import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import { Field, inputClass } from '../Auth/Login';

export default function UsersIndex({ users, roles, filters }) {
    const { auth, status } = usePage().props;
    const [search, setSearch] = useState(filters.search || '');
    const canCreate = auth.user.permissions.includes('users.create');
    const canUpdate = auth.user.permissions.includes('users.update');
    const createForm = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        role: '',
    });

    function searchUsers(event) {
        event.preventDefault();
        router.get('/users', { search }, { preserveState: true, replace: true });
    }

    function createUser(event) {
        event.preventDefault();
        createForm.post('/users', {
            onSuccess: () => createForm.reset(),
        });
    }

    return (
        <AppLayout
            title="Users"
            description="Manage sign-in accounts and assign each person an appropriate school role."
        >
            <Head title="Users" />
            {status && <StatusMessage>{status}</StatusMessage>}

            {canCreate && (
                <section className="mb-7 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <div className="mb-5">
                        <h2 className="font-semibold text-slate-900">Create a user account</h2>
                        <p className="mt-1 text-sm text-slate-500">Choose a primary role and a temporary password that meets the password policy.</p>
                    </div>
                    <form onSubmit={createUser} className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        <Field label="Full name" error={createForm.errors.name}>
                            <input
                                value={createForm.data.name}
                                onChange={(event) => createForm.setData('name', event.target.value)}
                                className={inputClass(createForm.errors.name)}
                                autoComplete="name"
                                required
                            />
                        </Field>
                        <Field label="Email address" error={createForm.errors.email}>
                            <input
                                type="email"
                                value={createForm.data.email}
                                onChange={(event) => createForm.setData('email', event.target.value)}
                                className={inputClass(createForm.errors.email)}
                                autoComplete="email"
                                required
                            />
                        </Field>
                        <Field label="Primary role" error={createForm.errors.role}>
                            <select
                                value={createForm.data.role}
                                onChange={(event) => createForm.setData('role', event.target.value)}
                                className={inputClass(createForm.errors.role)}
                                required
                            >
                                <option value="">Select a role</option>
                                {roles.map((role) => <option key={role} value={role}>{role}</option>)}
                            </select>
                        </Field>
                        <Field label="Temporary password" error={createForm.errors.password}>
                            <input
                                type="password"
                                value={createForm.data.password}
                                onChange={(event) => createForm.setData('password', event.target.value)}
                                className={inputClass(createForm.errors.password)}
                                autoComplete="new-password"
                                minLength={12}
                                required
                            />
                        </Field>
                        <Field label="Confirm password" error={createForm.errors.password_confirmation}>
                            <input
                                type="password"
                                value={createForm.data.password_confirmation}
                                onChange={(event) => createForm.setData('password_confirmation', event.target.value)}
                                className={inputClass(createForm.errors.password_confirmation)}
                                autoComplete="new-password"
                                minLength={12}
                                required
                            />
                        </Field>
                        <div className="flex items-end">
                            <button
                                type="submit"
                                disabled={createForm.processing}
                                className="w-full rounded-xl bg-teal-800 px-4 py-3 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-60"
                            >
                                Create account
                            </button>
                        </div>
                    </form>
                </section>
            )}

            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div className="flex flex-col gap-4 border-b border-slate-100 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <div>
                        <h2 className="font-semibold text-slate-900">School accounts</h2>
                        <p className="mt-1 text-sm text-slate-500">{users.total.toLocaleString()} total accounts</p>
                    </div>
                    <form onSubmit={searchUsers} className="flex w-full gap-2 sm:w-auto">
                        <input
                            type="search"
                            aria-label="Search users"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search name or email"
                            className="min-w-0 flex-1 rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-teal-700 sm:w-64"
                        />
                        <button className="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            Search
                        </button>
                    </form>
                </div>

                {users.data.length === 0 ? (
                    <div className="px-6 py-14 text-center">
                        <p className="font-medium text-slate-800">No user accounts found</p>
                        <p className="mt-1 text-sm text-slate-500">Try another search or create the first account.</p>
                    </div>
                ) : (
                    <>
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[700px] text-left text-sm">
                                <thead className="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th className="px-6 py-3">Name</th>
                                        <th className="px-6 py-3">Email</th>
                                        <th className="px-6 py-3">Assigned role</th>
                                        {canUpdate && <th className="px-6 py-3 text-right">Account access</th>}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {users.data.map((user) => (
                                        <UserRow key={user.id} user={user} roles={roles} canUpdate={canUpdate} />
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <Pagination links={users.links} />
                    </>
                )}
            </section>
        </AppLayout>
    );
}

function UserRow({ user, roles, canUpdate }) {
    const form = useForm({
        name: user.name,
        email: user.email,
        role: user.roles[0] || '',
        password: '',
        password_confirmation: '',
    });

    function save(event) {
        event.preventDefault();
        form.patch(`/users/${user.id}`, {
            preserveScroll: true,
            onSuccess: () => form.reset('password', 'password_confirmation'),
        });
    }

    return (
        <tr className="align-top">
            <td className="px-6 py-4 font-medium text-slate-900">
                {canUpdate ? (
                    <input
                        value={form.data.name}
                        onChange={(event) => form.setData('name', event.target.value)}
                        className="w-44 rounded-lg border border-transparent px-2 py-1.5 hover:border-slate-200 focus:border-teal-700 focus:outline-none"
                        aria-label={`Name for ${user.email}`}
                    />
                ) : user.name}
                {form.errors.name && <p className="mt-1 text-xs text-rose-700">{form.errors.name}</p>}
            </td>
            <td className="px-6 py-4 text-slate-600">
                {canUpdate ? (
                    <input
                        type="email"
                        value={form.data.email}
                        onChange={(event) => form.setData('email', event.target.value)}
                        className="w-56 rounded-lg border border-transparent px-2 py-1.5 hover:border-slate-200 focus:border-teal-700 focus:outline-none"
                        aria-label={`Email for ${user.name}`}
                    />
                ) : user.email}
                {form.errors.email && <p className="mt-1 text-xs text-rose-700">{form.errors.email}</p>}
            </td>
            <td className="px-6 py-4">
                {canUpdate ? (
                    <select
                        value={form.data.role}
                        onChange={(event) => form.setData('role', event.target.value)}
                        className="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-sm"
                        aria-label={`Role for ${user.name}`}
                    >
                        {roles.map((role) => <option key={role} value={role}>{role}</option>)}
                    </select>
                ) : (
                    <div className="flex flex-wrap gap-1.5">
                        {user.roles.map((role) => (
                            <span key={role} className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">{role}</span>
                        ))}
                    </div>
                )}
                {form.errors.role && <p className="mt-1 text-xs text-rose-700">{form.errors.role}</p>}
            </td>
            {canUpdate && (
                <td className="px-6 py-4 text-right">
                    <button
                        type="button"
                        onClick={save}
                        disabled={form.processing}
                        className="rounded-lg px-3 py-1.5 text-xs font-semibold text-teal-800 hover:bg-teal-50 disabled:opacity-50"
                    >
                        Save changes
                    </button>
                    {form.errors.password && <p className="mt-1 text-right text-xs text-rose-700">{form.errors.password}</p>}
                </td>
            )}
        </tr>
    );
}

function Pagination({ links }) {
    if (links.length <= 3) {
        return null;
    }

    return (
        <nav aria-label="Pagination" className="flex flex-wrap justify-center gap-1 border-t border-slate-100 px-4 py-4">
            {links.map((link, index) => (
                <button
                    key={`${link.label}-${index}`}
                    type="button"
                    disabled={!link.url}
                    onClick={() => link.url && router.visit(link.url, { preserveScroll: true })}
                    className={`rounded-lg px-3 py-2 text-sm ${
                        link.active
                            ? 'bg-teal-800 font-semibold text-white'
                            : 'text-slate-600 hover:bg-slate-100 disabled:text-slate-300'
                    }`}
                    aria-current={link.active ? 'page' : undefined}
                >
                    <span>{link.label.replace(/<[^>]*>/g, '').replaceAll('&laquo;', '‹').replaceAll('&raquo;', '›')}</span>
                </button>
            ))}
        </nav>
    );
}

function StatusMessage({ children }) {
    return <div role="status" className="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{children}</div>;
}
