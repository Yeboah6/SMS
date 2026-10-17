import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import PageAlerts from '../../Components/Exams/PageAlerts';

export default function StaffShow({ member, can }) {
    function deleteStaff() {
        if (window.confirm(`Delete ${member.name}? This also removes their sign-in account. Staff with teaching assignments cannot be deleted.`)) {
            router.delete(`/staff/${member.id}`);
        }
    }

    return (
        <AppLayout title={member.name} description={`${member.staffNumber}${member.designation ? ` · ${member.designation}` : ''}`}>
            <Head title={member.name} />
            <PageAlerts />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <Link href="/staff" className="text-sm font-semibold text-teal-800 hover:text-teal-950">Back to staff</Link>
                <div className="flex items-center gap-4 text-sm font-semibold">
                    {can.update && <Link href={`/staff/${member.id}/edit`} className="text-teal-800 hover:text-teal-950">Edit profile</Link>}
                    {can.delete && <button type="button" onClick={deleteStaff} className="text-rose-700 hover:text-rose-900">Delete</button>}
                </div>
            </div>

            <div className="grid gap-6 xl:grid-cols-2">
                <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <div className="flex items-center justify-between gap-3">
                        <h2 className="font-semibold text-slate-900">Profile</h2>
                        <span className={`rounded-full px-2.5 py-1 text-xs font-semibold capitalize ${member.status === 'active' ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-600'}`}>{member.status}</span>
                    </div>
                    <dl className="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                        <Detail label="Staff number" value={member.staffNumber} />
                        <Detail label="Full name" value={member.name} />
                        <Detail label="Phone" value={member.phone} />
                        <Detail label="Joining date" value={member.joiningDate ? new Date(`${member.joiningDate}T00:00:00`).toLocaleDateString() : null} />
                        <Detail label="Department" value={member.department} />
                        <Detail label="Designation" value={member.designation} />
                        <div className="sm:col-span-2">
                            <Detail label="Qualifications" value={member.qualifications} />
                        </div>
                    </dl>
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <h2 className="font-semibold text-slate-900">Sign-in account</h2>
                    {member.hasAccount ? (
                        <dl className="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                            <Detail label="Email" value={member.email} />
                            <Detail label="Role" value={member.role} />
                            <Detail label="Teaching" value={member.isTeacher ? 'Can be assigned classes and subjects' : 'Non-teaching staff'} />
                        </dl>
                    ) : (
                        <p className="mt-4 rounded-xl bg-slate-50 px-3 py-4 text-sm text-slate-500">No sign-in account. Edit the profile to set a role and password.</p>
                    )}
                    {can.manageUsers && <Link href="/users" className="mt-4 inline-block text-sm font-semibold text-teal-800 hover:text-teal-950">Manage user accounts and roles</Link>}
                </section>
            </div>
        </AppLayout>
    );
}

function Detail({ label, value }) {
    return (
        <div>
            <dt className="text-xs font-medium text-slate-500">{label}</dt>
            <dd className="mt-1 text-slate-900">{value || '—'}</dd>
        </div>
    );
}