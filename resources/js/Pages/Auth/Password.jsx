import { Head, useForm } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import PageAlerts from '../../Components/Exams/PageAlerts';
import AppLayout from '../../Layouts/AppLayout';
import { Field, inputClass } from './Login';

export default function ProfilePassword({ forced }) {
    const form = useForm({ current_password: '', password: '', password_confirmation: '' });

    function submit(event) {
        event.preventDefault();
        form.put('/profile/password', { onSuccess: () => form.reset() });
    }

    return (
        <AppLayout
            title="Change password"
            description={forced ? 'Choose a new password before you continue.' : 'Update the password you use to sign in.'}
        >
            <Head title="Change password" />
            <PageAlerts />
            {forced && (
                <div role="alert" className="mb-6 flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                    <KeyRound size={18} className="mt-0.5 shrink-0" />
                    <p>You are using a temporary password. Set your own password to continue.</p>
                </div>
            )}
            <section className="max-w-xl rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <form onSubmit={submit} className="space-y-5">
                    <Field label={forced ? 'Temporary password' : 'Current password'} error={form.errors.current_password}>
                        <input
                            type="password"
                            autoComplete="current-password"
                            value={form.data.current_password}
                            onChange={(event) => form.setData('current_password', event.target.value)}
                            className={inputClass(form.errors.current_password)}
                            required
                        />
                    </Field>
                    <Field label="New password" error={form.errors.password}>
                        <input
                            type="password"
                            autoComplete="new-password"
                            value={form.data.password}
                            onChange={(event) => form.setData('password', event.target.value)}
                            className={inputClass(form.errors.password)}
                            required
                        />
                    </Field>
                    <Field label="Confirm new password" error={form.errors.password_confirmation}>
                        <input
                            type="password"
                            autoComplete="new-password"
                            value={form.data.password_confirmation}
                            onChange={(event) => form.setData('password_confirmation', event.target.value)}
                            className={inputClass(form.errors.password_confirmation)}
                            required
                        />
                    </Field>
                    <p className="text-xs text-slate-500">At least 8 characters, with letters and numbers.</p>
                    <button
                        disabled={form.processing}
                        className="rounded-xl bg-teal-800 px-5 py-2.5 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-60"
                    >
                        Save password
                    </button>
                </form>
            </section>
        </AppLayout>
    );
}