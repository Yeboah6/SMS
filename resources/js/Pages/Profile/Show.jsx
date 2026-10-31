import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import PageAlerts from '../../Components/Exams/PageAlerts';
import { Field, inputClass } from '../Auth/Login';

const dateTime = (value) => (value ? new Date(value.replace(' ', 'T')).toLocaleString() : null);
const date = (value) => (value ? new Date(`${value.slice(0, 10)}T00:00:00`).toLocaleDateString() : null);

export default function ProfileShow({ account, profile, locked }) {
    const initials = account.name.split(' ').filter(Boolean).slice(0, 2).map((part) => part[0].toUpperCase()).join('');

    return (
        <AppLayout title="My profile" description="Your account details, password and where you fit in the school.">
            <Head title="My profile" />
            <PageAlerts />

            <section className="mb-6 flex flex-wrap items-center gap-5 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <span aria-hidden="true" className="flex size-16 shrink-0 items-center justify-center rounded-full bg-teal-100 text-xl font-semibold text-teal-800">{initials}</span>
                <div className="min-w-0 flex-1">
                    <h2 className="truncate text-xl font-bold tracking-tight text-slate-950">{account.name}</h2>
                    <p className="mt-0.5 truncate text-sm text-slate-500">{account.email}</p>
                    <div className="mt-2 flex flex-wrap gap-1.5">
                        {account.roles.length === 0 ? <span className="text-xs text-slate-400">No role assigned</span> : account.roles.map((role) => <span key={role} className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">{role}</span>)}
                    </div>
                </div>
                <dl className="grid gap-1 text-xs text-slate-500">
                    <div>Member since <span className="font-medium text-slate-700">{date(account.createdAt) || '—'}</span></div>
                    <div>Email {account.emailVerifiedAt ? <span className="font-medium text-emerald-700">verified</span> : <span className="font-medium text-slate-700">not verified</span>}</div>
                </dl>
            </section>

            <div className="grid gap-6 xl:grid-cols-2">
                <AccountForm account={account} locked={locked} profile={profile} />
                <PasswordForm />
            </div>

            {profile && (
                <section className="mt-6 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <h2 className="font-semibold text-slate-900">{profile.label}</h2>
                    <p className="mt-1 text-sm text-slate-500">These details come from the school's records. To change them, contact the school office.</p>
                    {profile.details.length === 0 ? (
                        <p className="mt-4 rounded-xl bg-slate-50 px-3 py-4 text-sm text-slate-500">No children are linked to this account yet.</p>
                    ) : (
                        <dl className="mt-4 grid gap-4 text-sm sm:grid-cols-2 xl:grid-cols-3">
                            {profile.details.map((detail, index) => (
                                <div key={`${detail.label}-${index}`}>
                                    <dt className="text-xs font-medium text-slate-500">{detail.label}</dt>
                                    <dd className="mt-1 text-slate-900">{/^\d{4}-\d{2}-\d{2}$/.test(detail.value || '') ? date(detail.value) : detail.value || '—'}</dd>
                                </div>
                            ))}
                        </dl>
                    )}
                </section>
            )}
        </AppLayout>
    );
}

function AccountForm({ account, locked, profile }) {
    const form = useForm({ name: account.name, email: account.email, current_password: '' });
    const emailChanged = form.data.email.trim().toLowerCase() !== account.email.toLowerCase();

    function submit(event) {
        event.preventDefault();
        form.patch('/profile', { preserveScroll: true, onFinish: () => form.reset('current_password') });
    }

    return (
        <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
            <h2 className="font-semibold text-slate-900">Account details</h2>
            {locked.length > 0 && <p className="mt-1 text-sm text-slate-500">Your {locked.join(' and ')} {locked.length > 1 ? 'are' : 'is'} kept on your {profile.type} record, so {locked.length > 1 ? 'they are' : 'it is'} read-only here.</p>}
            <form onSubmit={submit} className="mt-5 grid gap-4">
                <Field label="Full name" error={form.errors.name}>
                    <input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} disabled={locked.includes('name')} className={inputClass(form.errors.name)} autoComplete="name" required />
                </Field>
                <Field label="Email address (your sign-in)" error={form.errors.email}>
                    <input type="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} disabled={locked.includes('email')} className={inputClass(form.errors.email)} autoComplete="email" required />
                </Field>
                {emailChanged && !locked.includes('email') && (
                    <Field label="Current password (needed to change your email)" error={form.errors.current_password}>
                        <input type="password" value={form.data.current_password} onChange={(event) => form.setData('current_password', event.target.value)} className={inputClass(form.errors.current_password)} autoComplete="current-password" required />
                    </Field>
                )}
                <div><button disabled={form.processing || (locked.includes('name') && locked.includes('email'))} className="rounded-xl bg-teal-800 px-5 py-3 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-60">{form.processing ? 'Saving…' : 'Save details'}</button></div>
            </form>
        </section>
    );
}

function PasswordForm() {
    const [show, setShow] = useState(false);
    const form = useForm({ current_password: '', password: '', password_confirmation: '' });

    function submit(event) {
        event.preventDefault();
        form.put('/profile/password', { preserveScroll: true, onSuccess: () => form.reset(), onError: () => form.reset('password', 'password_confirmation') });
    }

    const type = show ? 'text' : 'password';

    return (
        <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
            <h2 className="font-semibold text-slate-900">Change password</h2>
            <p className="mt-1 text-sm text-slate-500">Use a long password you do not use anywhere else.</p>
            <form onSubmit={submit} className="mt-5 grid gap-4">
                <Field label="Current password" error={form.errors.current_password}>
                    <input type={type} value={form.data.current_password} onChange={(event) => form.setData('current_password', event.target.value)} className={inputClass(form.errors.current_password)} autoComplete="current-password" required />
                </Field>
                <Field label="New password" error={form.errors.password}>
                    <input type={type} value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} className={inputClass(form.errors.password)} autoComplete="new-password" required />
                </Field>
                <Field label="Confirm new password" error={form.errors.password_confirmation}>
                    <input type={type} value={form.data.password_confirmation} onChange={(event) => form.setData('password_confirmation', event.target.value)} className={inputClass(form.errors.password_confirmation)} autoComplete="new-password" required />
                </Field>
                <div className="flex flex-wrap items-center gap-4">
                    <button disabled={form.processing} className="rounded-xl bg-teal-800 px-5 py-3 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-60">{form.processing ? 'Saving…' : 'Change password'}</button>
                    <label className="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" checked={show} onChange={(event) => setShow(event.target.checked)} className="size-4 rounded border-slate-300 text-teal-700 focus:ring-teal-700" />Show passwords</label>
                </div>
            </form>
        </section>
    );
}