import { Head, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';

export default function RolesIndex({ roles, permissionGroups }) {
    const { auth, status } = usePage().props;
    const [selectedRole, setSelectedRole] = useState(roles[0]?.id ?? null);
    const selected = roles.find((role) => role.id === selectedRole);
    const canManage = auth.user.permissions.includes('permissions.assign');

    return (
        <AppLayout title="Roles & permissions" description="Review school roles and grant only the permissions each role needs.">
            <Head title="Roles & permissions" />
            {status && <div role="status" className="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{status}</div>}
            <div className="grid gap-6 xl:grid-cols-[280px_minmax(0,1fr)]">
                <section className="rounded-2xl border border-slate-200 bg-white p-3">
                    <h2 className="px-3 py-3 text-sm font-semibold text-slate-800">Primary roles</h2>
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
                                <span className="text-sm font-medium">{role.name}</span>
                                <span className="text-xs text-slate-500">{role.userCount} users</span>
                            </button>
                        ))}
                    </div>
                    <p className="mx-3 mt-4 border-t border-slate-100 pt-4 text-xs leading-5 text-slate-500">
                        Primary role names are fixed. Change a role's permissions here; custom roles are intentionally not created in this phase.
                    </p>
                </section>

                {selected && (
                    <RoleEditor
                        key={selected.id}
                        role={selected}
                        permissionGroups={permissionGroups}
                        canManage={canManage}
                    />
                )}
            </div>
        </AppLayout>
    );
}

function RoleEditor({ role, permissionGroups, canManage }) {
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
                {canManage && (
                    <button
                        onClick={save}
                        disabled={form.processing}
                        className="rounded-xl bg-teal-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-60"
                    >
                        Save permissions
                    </button>
                )}
            </div>
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
                                            disabled={!canManage || role.name === 'Super Admin'}
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
