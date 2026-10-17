import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import PageAlerts from '../../Components/Exams/PageAlerts';

const PROFILE_TYPES = { staff: 'Staff profile', student: 'Student profile', guardian: 'Guardian' };
const dateTime = (value) => (value ? new Date(value.replace(' ', 'T')).toLocaleString() : null);

export default function UsersShow({ account, profile, permissions, can }) {
    const areas = Object.entries(permissions);

    function deactivate() {
        if (window.confirm(`Deactivate the account for ${account.name}? They will no longer be able to sign in. Historical records are retained.`)) {
            router.delete(`/users/${account.id}`);
        }
    }

    return (
        <AppLayout title={account.name} description={account.email}>
            <Head title={account.name} />
            <PageAlerts />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <Link href="/users" className="text-sm font-semibold text-teal-800 hover:text-teal-950">Back to users</Link>
                <div className="flex items-center gap-4 text-sm font-semibold">
                    {can.edit && <Link href={`/users/${account.id}/edit`} className="text-teal-800 hover:text-teal-950">Edit account</Link>}
                    {can.deactivate && <button type="button" onClick={deactivate} className="text-rose-700 hover:text-rose-900">Deactivate</button>}
                </div>
            </div>

            <div className="mb-6 grid gap-6 xl:grid-cols-2">
                <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <h2 className="font-semibold text-slate-900">Account</h2>
                    <dl className="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                        <Row label="Name" value={account.name} />
                        <Row label="Email" value={account.email} />
                        <div>
                            <dt className="text-xs font-medium text-slate-500">Role</dt>
                            <dd className="mt-1 flex flex-wrap gap-1.5">
                                {account.roles.length === 0 ? '—' : account.roles.map((role) => <span key={role} className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">{role}</span>)}
                            </dd>
                        </div>
                        <Row label="Created" value={dateTime(account.createdAt)} />
                        <Row label="Email verified" value={dateTime(account.emailVerifiedAt) || 'Not verified'} />
                    </dl>
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <h2 className="font-semibold text-slate-900">Linked profile</h2>
                    {profile ? (
                        <>
                            <p className="mt-4 text-sm text-slate-700">
                                This account belongs to {profile.href ? <Link href={profile.href} className="font-semibold text-teal-800 hover:text-teal-950">{profile.label}</Link> : profile.label} ({PROFILE_TYPES[profile.type]}).
                            </p>
                            <p className="mt-3 rounded-xl bg-slate-50 px-3 py-3 text-xs leading-5 text-slate-600">
                                {profile.locked.includes('name')
                                    ? 'Name, email and role are managed on the staff profile so the two stay in step. A password can be reset from the Edit page.'
                                    : 'The role is fixed by the profile. Name, email and password can be edited here.'}
                            </p>
                        </>
                    ) : (
                        <p className="mt-4 rounded-xl bg-slate-50 px-3 py-4 text-sm text-slate-500">Not linked to a staff, student or guardian profile.</p>
                    )}
                </section>
            </div>

            <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <h2 className="font-semibold text-slate-900">What this account can do</h2>
                <p className="mt-1 text-sm text-slate-500">Permissions come from the role. Change what a role allows on the Roles page.</p>
                {areas.length === 0 ? (
                    <p className="mt-4 rounded-xl bg-slate-50 px-3 py-4 text-sm text-slate-500">No permissions.</p>
                ) : (
                    <div className="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {areas.map(([area, names]) => (
                            <div key={area} className="rounded-xl border border-slate-100 p-4">
                                <h3 className="text-sm font-semibold capitalize text-slate-800">{area.replaceAll('_', ' ')}</h3>
                                <div className="mt-2 flex flex-wrap gap-1.5">
                                    {names.map((name) => <span key={name} className="rounded-md bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{name.slice(name.indexOf('.') + 1).replaceAll('_', ' ').replaceAll('.', ' ')}</span>)}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </section>
        </AppLayout>
    );
}

function Row({ label, value }) {
    return (
        <div>
            <dt className="text-xs font-medium text-slate-500">{label}</dt>
            <dd className="mt-1 break-words text-slate-900">{value || '—'}</dd>
        </div>
    );
}