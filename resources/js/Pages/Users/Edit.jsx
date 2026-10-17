import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { Field, inputClass } from '../Auth/Login';

export default function UsersEdit({ account, roles, profile, locked }) {
    const form = useForm({
        name: account.name,
        email: account.email,
        role: account.role,
        password: '',
        password_confirmation: '',
    });
    const set = (key) => (event) => form.setData(key, event.target.value);

    function submit(event) {
        event.preventDefault();
        form.patch(`/users/${account.id}`, {
            preserveScroll: true,
            onError: () => form.reset('password', 'password_confirmation'),
        });
    }

    return (
        <AppLayout title={`Edit ${account.name}`} description="Update the account, change its role or reset the password.">
            <Head title={`Edit ${account.name}`} />

            {profile && (
                <p role="status" className="mb-6 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                    This account belongs to {profile.href ? <Link href={profile.href} className="font-semibold underline">{profile.label}</Link> : profile.label}.
                    {' '}{locked.includes('name') ? 'Name, email and role are changed on the staff profile. You can still reset the password here.' : 'Its role is fixed by the profile.'}
                </p>
            )}

            <form onSubmit={submit} className="grid max-w-4xl gap-6">
                <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
                    <h2 className="font-semibold text-slate-900">Account</h2>
                    <div className="mt-5 grid gap-5 sm:grid-cols-2">
                        <Field label="Full name" error={form.errors.name}>
                            <input value={form.data.name} onChange={set('name')} disabled={locked.includes('name')} className={inputClass(form.errors.name)} autoComplete="name" required />
                        </Field>
                        <Field label="Email address" error={form.errors.email}>
                            <input type="email" value={form.data.email} onChange={set('email')} disabled={locked.includes('email')} className={inputClass(form.errors.email)} autoComplete="email" required />
                        </Field>
                        <Field label="School role" error={form.errors.role}>
                            <select value={form.data.role} onChange={set('role')} disabled={locked.includes('role')} className={inputClass(form.errors.role)} required>
                                {roles.map((role) => <option key={role} value={role}>{role}</option>)}
                            </select>
                        </Field>
                    </div>
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
                    <h2 className="font-semibold text-slate-900">Reset password</h2>
                    <p className="mt-1 text-sm text-slate-500">Leave blank to keep the current password. Use at least 12 characters.</p>
                    <div className="mt-5 grid gap-5 sm:grid-cols-2">
                        <Field label="New password" error={form.errors.password}>
                            <input type="password" value={form.data.password} onChange={set('password')} className={inputClass(form.errors.password)} autoComplete="new-password" minLength={12} />
                        </Field>
                        <Field label="Confirm new password" error={form.errors.password_confirmation}>
                            <input type="password" value={form.data.password_confirmation} onChange={set('password_confirmation')} className={inputClass(form.errors.password_confirmation)} autoComplete="new-password" minLength={12} required={form.data.password !== ''} />
                        </Field>
                    </div>
                </section>

                <div className="flex flex-wrap gap-3">
                    <button disabled={form.processing} className="rounded-xl bg-teal-800 px-5 py-3 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-60">{form.processing ? 'Saving…' : 'Save changes'}</button>
                    <Link href={`/users/${account.id}`} className="rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</Link>
                </div>
            </form>
        </AppLayout>
    );
}