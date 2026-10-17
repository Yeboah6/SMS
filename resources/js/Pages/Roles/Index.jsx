import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';

export default function RolesIndex({ roles, permissionGroups }) {
    const { auth, status, errors = {} } = usePage().props;
    const [selectedRole, setSelectedRole] = useState(roles[0]?.id ?? null);
    const selected = roles.find((role) => role.id === selectedRole);
    const canManagePermissions = auth.user.permissions.includes('permissions.assign')
        && auth.user.permissions.includes('roles.update');
    const canCreate = auth.user.permissions.includes('roles.create');
    const canDelete = auth.user.permissions.includes('roles.delete');
    const createForm = useForm({ name: '' });

    function createRole(event) {
        event.preventDefault();
        createForm.post('/roles', { onSuccess: () => createForm.reset() });
    }

    function deleteRole(role) {
        if (!window.confirm(`Delete the custom role "${role.name}"?`)) {
            return;
        }

        router.delete(`/roles/${role.id}`, {
            preserveScroll: true,
            onSuccess: () => setSelectedRole(roles.find((item) => item.id !== role.id)?.id ?? null),
        });
    }

    return (
        <AppLayout title="Roles & permissions" description="Review school roles and grant only the permissions each role needs.">
            <Head title="Roles & permissions" />
            {status && <div role="status" className="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{status}</div>}
            {errors.role && <div role="alert" className="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{errors.role}</div>}
            <div className="grid gap-6 xl:grid-cols-[280px_minmax(0,1fr)]">
                <section className="h-fit rounded-2xl border border-slate-200 bg-white p-3">
                    <h2 className="px-3 py-3 text-sm font-semibold text-slate-800">School roles</h2>
                    {canCreate && (
                        <form onSubmit={createRole} className="space-y-2 border-b border-slate-100 px-3 pb-4">
                            <label htmlFor="role-name" className="block text-xs font-medium text-slate-600">Create a custom role</label>
                            <input
                                id="role-name"
                                value={createForm.data.name}
                                onChange={(event) => createForm.setData('name', event.target.value)}
                                maxLength={255}
                                required
                                className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none focus:border-teal-700"
                                placeholder="e.g. Attendance Officer"
                            />
                            {createForm.errors.name && <p role="alert" className="text-xs text-rose-700">{createForm.errors.name}</p>}
                            <button
                                type="submit"
                                disabled={createForm.processing}
                                className="w-full rounded-lg bg-teal-800 px-3 py-2 text-xs font-semibold text-white hover:bg-teal-900 disabled:opacity-60"
                            >
                                Add role
                            </button>
                        </form>
                    )}
                    <div className="space-y-1">
                        {roles.map((role) => (
                            <button
                                key={role.id}
                                type="button"
                                onClick={() => setSelectedRole(role.id)}
                                className={`flex w-full items-center justify-between rounded-xl px-3 py-3 text-left ${
                                    selectedRole === role.id ? 'bg-teal-50 text-teal-900' : 'text-slate-700 hover:bg-slate-50'
                                }`}
                            >
                                <span className="flex flex-col text-left">
                                    <span className="text-sm font-medium">{role.name}</span>
                                    {!role.isPrimary && <span className="text-[11px] text-slate-500">Custom role</span>}
                                </span>
                                <span className="text-xs text-slate-500">{role.userCount} users</span>
                            </button>
                        ))}
                    </div>
                    <p className="mx-3 mt-4 border-t border-slate-100 pt-4 text-xs leading-5 text-slate-500">
                        The eight primary role names are fixed. Custom roles can be created and assigned without changing the primary role set.
                    </p>
                </section>

                {selected && (
                    <RoleEditor
                        key={selected.id}
                        role={selected}
                        permissionGroups={permissionGroups}
                        canManagePermissions={canManagePermissions}
                        canDelete={canDelete}
                        onDelete={() => deleteRole(selected)}
                    />
                )}
            </div>
        </AppLayout>
    );
}

function RoleEditor({ role, permissionGroups, canManagePermissions, canDelete, onDelete }) {
    const form = useForm({ permission_ids: role.permissionIds });
    const allPermissions = useMemo(
        () => Object.values(permissionGroups).flat(),
        [permissionGroups],
    );
    const assignedCount = form.data.permission_ids.length;

    function togglePermission(permissionId, checked) {
        const next = checked
            ? [...form.data.permission_ids, permissionId]
            : form.data.permission_ids.filter((id) => id !== permissionId);
        form.setData('permission_ids', next);
    }

    function save(event) {
        event.preventDefault();
        form.put(`/roles/${role.id}/permissions`, { preserveScroll: true });
    }

    return (
        <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <div className="flex flex-col gap-3 border-b border-slate-100 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div>
                    <h2 className="text-lg font-semibold text-slate-950">{role.name}</h2>
                    <p className="mt-1 text-sm text-slate-500">{assignedCount} permissions assigned</p>
                </div>
                <div className="flex items-center gap-2">
                    {canDelete && !role.isPrimary && role.userCount === 0 && (
                        <button
                            type="button"
                            onClick={onDelete}
                            className="rounded-xl border border-rose-200 px-4 py-2.5 text-sm font-semibold text-rose-700 hover:bg-rose-50"
                        >
                            Delete role
                        </button>
                    )}
                {canManagePermissions && role.name !== 'Super Admin' && (
                    <button
                        onClick={save}
                        disabled={form.processing}
                        className="rounded-xl bg-teal-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-60"
                    >
                        Save permissions
                    </button>
                )}
                </div>
            </div>
            {role.name === 'Super Admin' && (
                <p className="border-b border-slate-100 bg-amber-50 px-5 py-3 text-sm text-amber-900 sm:px-6">
                    The Super Admin role retains the complete permission catalog and cannot be changed here.
                </p>
            )}
            <form onSubmit={save}>
                <div className="grid gap-5 p-5 sm:grid-cols-2 sm:p-6 2xl:grid-cols-3">
                    {Object.entries(permissionGroups).map(([group, permissions]) => (
                        <fieldset key={group} className="rounded-xl border border-slate-200 p-4">
                            <legend className="px-1 text-sm font-semibold capitalize text-slate-800">{group.replaceAll('_', ' ')}</legend>
                            <div className="mt-2 space-y-2.5">
                                {permissions.map((permission) => (
                                    <label key={permission.id} className="flex items-start gap-2.5 text-sm text-slate-600">
                                        <input
                                            type="checkbox"
                                            checked={form.data.permission_ids.includes(permission.id)}
                                            onChange={(event) => togglePermission(permission.id, event.target.checked)}
                                            disabled={!canManagePermissions || role.name === 'Super Admin'}
                                            className="mt-0.5 size-4 rounded border-slate-300 text-teal-700 focus:ring-teal-700 disabled:opacity-50"
                                        />
                                        <span className="break-all">{permission.name}</span>
                                    </label>
                                ))}
                            </div>
                        </fieldset>
                    ))}
                </div>
                {Object.keys(form.errors).length > 0 && (
                    <p role="alert" className="px-6 pb-4 text-sm text-rose-700">
                        Permission changes could not be saved. Refresh the page and try again.
                    </p>
                )}
            </form>
            {!allPermissions.length && <p className="p-6 text-sm text-slate-500">No permissions have been registered.</p>}
        </section>
    );
}
