import { Head } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import StaffForm from '../../Components/Staffs/StaffForm';

export default function StaffCreate({ roles, departments, designations, canManageUsers }) {
    return (
        <AppLayout title="Create staff profile" description="Add a staff member. Their sign-in account is created with the role and password you set.">
            <Head title="Create staff profile" />
            <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
                <StaffForm roles={roles} departments={departments} designations={designations} canManageUsers={canManageUsers} />
            </section>
        </AppLayout>
    );
}