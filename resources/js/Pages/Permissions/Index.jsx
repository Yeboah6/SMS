import { Head } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';

export default function PermissionsIndex({ groups }) {
    const permissions = Object.values(groups).flat();

    return (
        <AppLayout
            title="Permissions"
            description="The application permission catalog used by role assignments and server-side authorization."
        >
            <Head title="Permissions" />
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
                                <li key={permission.id} className="rounded-lg bg-slate-50 px-3 py-2 font-mono text-xs text-slate-600">
                                    {permission.name}
                                </li>
                            ))}
                        </ul>
                    </section>
                ))}
            </div>
        </AppLayout>
    );
}
