import { Head, router, useForm, usePage } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';

export default function PermissionsIndex({ groups }) {
    const { auth, status, errors = {} } = usePage().props;
    const permissions = Object.values(groups).flat();
    const canCreate = auth.user.permissions.includes('permissions.create');
    const canDelete = auth.user.permissions.includes('permissions.delete');
    const createForm = useForm({ name: '' });

    function createPermission(event) {
        event.preventDefault();
        createForm.post('/permissions', { onSuccess: () => createForm.reset() });
    }

    function deletePermission(permission) {
        if (!window.confirm(`Delete the custom permission "${permission.name}"?`)) {
            return;
        }

        router.delete(`/permissions/${permission.id}`, { preserveScroll: true });
    }

    return (
        <AppLayout
            title="Permissions"
            description="The application permission catalog used by role assignments and server-side authorization."
        >
            <Head title="Permissions" />
            {status && <div role="status" className="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{status}</div>}
            {errors.permission && <div role="alert" className="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{errors.permission}</div>}
            {canCreate && (
                <form onSubmit={createPermission} className="mb-6 grid gap-3 rounded-2xl border border-slate-200 bg-white p-5 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
                    <div>
                        <label htmlFor="permission-name" className="mb-1 block text-sm font-medium text-slate-700">Create a custom permission</label>
                        <input
                            id="permission-name"
                            value={createForm.data.name}
                            onChange={(event) => createForm.setData('name', event.target.value)}
                            maxLength={255}
                            required
                            className="w-full rounded-xl border border-slate-200 px-3 py-2.5 font-mono text-sm outline-none focus:border-teal-700"
                            placeholder="e.g. attendance.export"
                        />
                        <p className="mt-1 text-xs text-slate-500">Use a lowercase dotted key; assign it to a role from Roles & permissions.</p>
                        {createForm.errors.name && <p role="alert" className="mt-1 text-xs text-rose-700">{createForm.errors.name}</p>}
                    </div>
                    <button
                        type="submit"
                        disabled={createForm.processing}
                        className="rounded-xl bg-teal-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-60"
                    >
                        Add permission
                    </button>
                </form>
            )}
            <div className="mb-6 flex items-center gap-3 rounded-2xl border border-teal-100 bg-teal-50 p-4 text-sm text-teal-950">
                <KeyRound size={18} className="shrink-0 text-teal-800" />
                <p>
                    <span className="font-semibold">{permissions.length} permissions</span>
                    <span className="text-teal-800"> · Authorization is checked by Laravel, not by navigation visibility alone.</span>
                </p>
            </div>
            <div className="grid gap-5 sm:grid-cols-2 2xl:grid-cols-3">
                {Object.entries(groups).map(([group, items]) => (
                    <section key={group} className="rounded-2xl border border-slate-200 bg-white p-5">
                        <div className="flex items-center justify-between">
                            <h2 className="font-semibold capitalize text-slate-900">{group.replaceAll('_', ' ')}</h2>
                            <span className="rounded-full bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600">{items.length}</span>
                        </div>
                        <ul className="mt-4 space-y-2">
                            {items.map((permission) => (
                                <li key={permission.id} className="flex items-center justify-between gap-3 rounded-lg bg-slate-50 px-3 py-2">
                                    <span className="break-all font-mono text-xs text-slate-600">{permission.name}</span>
                                    {canDelete && !permission.isSystem && permission.roleCount === 0 && (
                                        <button
                                            type="button"
                                            onClick={() => deletePermission(permission)}
                                            className="shrink-0 text-xs font-semibold text-rose-700 hover:text-rose-900"
                                        >
                                            Delete
                                        </button>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </section>
                ))}
            </div>
            <p className="mt-5 text-xs leading-5 text-slate-500">
                Permission keys are immutable. A permission can be removed only after it has been unassigned from every role.
            </p>
        </AppLayout>
    );
}
