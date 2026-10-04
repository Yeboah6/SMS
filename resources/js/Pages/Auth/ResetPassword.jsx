import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, GraduationCap, LoaderCircle } from 'lucide-react';
import { Field, inputClass } from './Login';

export default function ResetPassword({ email, token }) {
    const form = useForm({
        token,
        email: email || '',
        password: '',
        password_confirmation: '',
    });

    function submit(event) {
        event.preventDefault();
        form.post('/reset-password', {
            onFinish: () => form.reset('password', 'password_confirmation'),
        });
    }

    return (
        <>
            <Head title="Choose a new password" />
            <main className="flex min-h-screen items-center justify-center bg-slate-50 px-5 py-12">
                <section className="w-full max-w-md rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-9">
                    <Link href="/login" className="inline-flex items-center gap-2 text-sm font-medium text-slate-500 hover:text-teal-800">
                        <ArrowLeft size={16} />
                        Back to sign in
                    </Link>
                    <div className="mt-8 flex size-12 items-center justify-center rounded-2xl bg-teal-50 text-teal-800">
                        <GraduationCap size={24} />
                    </div>
                    <h1 className="mt-5 text-2xl font-bold tracking-tight text-slate-950">Choose a new password</h1>
                    <p className="mt-2 text-sm leading-6 text-slate-500">Use a strong password you do not use elsewhere.</p>
                    <form onSubmit={submit} className="mt-6 space-y-5">
                        <Field label="Email address" error={form.errors.email}>
                            <input
                                type="email"
                                autoComplete="email"
                                required
                                value={form.data.email}
                                onChange={(event) => form.setData('email', event.target.value)}
                                className={inputClass(form.errors.email)}
                            />
                        </Field>
                        <Field label="New password" error={form.errors.password}>
                            <input
                                type="password"
                                autoComplete="new-password"
                                required
                                value={form.data.password}
                                onChange={(event) => form.setData('password', event.target.value)}
                                className={inputClass(form.errors.password)}
                            />
                        </Field>
                        <Field label="Confirm new password" error={form.errors.password_confirmation}>
                            <input
                                type="password"
                                autoComplete="new-password"
                                required
                                value={form.data.password_confirmation}
                                onChange={(event) => form.setData('password_confirmation', event.target.value)}
                                className={inputClass(form.errors.password_confirmation)}
                            />
                        </Field>
                        <button
                            disabled={form.processing}
                            className="flex w-full items-center justify-center gap-2 rounded-xl bg-teal-800 px-4 py-3 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-60"
                        >
                            {form.processing && <LoaderCircle size={17} className="animate-spin" />}
                            Update password
                        </button>
                    </form>
                </section>
            </main>
        </>
    );
}
