import { Head } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import StaffForm from '../../Components/Staffs/StaffForm';

export default function StaffEdit({ member, roles, departments, designations, canManageUsers }) {
    return (
        <AppLayout title={`Edit ${member.name}`} description="Update staff details, role and sign-in password.">
            <Head title={`Edit ${member.name}`} />
            {!member.hasAccount && (
                <div role="status" className="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    This profile has no sign-in account yet. Saving with a role and password will create one.
                </div>
            )}
            <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
                <StaffForm member={member} roles={roles} departments={departments} designations={designations} canManageUsers={canManageUsers} />
            </section>
        </AppLayout>
    );
}