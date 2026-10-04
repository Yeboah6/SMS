import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, GraduationCap, LoaderCircle } from 'lucide-react';
import { Field, inputClass } from './Login';

export default function ForgotPassword() {
    const { status } = usePage().props;
    const form = useForm({ email: '' });

    function submit(event) {
        event.preventDefault();
        form.post('/forgot-password');
    }

    return (
        <>
            <Head title="Reset password" />
            <main className="flex min-h-screen items-center justify-center bg-slate-50 px-5 py-12">
                <section className="w-full max-w-md rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-9">
                    <Link href="/login" className="inline-flex items-center gap-2 text-sm font-medium text-slate-500 hover:text-teal-800">
                        <ArrowLeft size={16} />
                        Back to sign in
                    </Link>
                    <div className="mt-8 flex size-12 items-center justify-center rounded-2xl bg-teal-50 text-teal-800">
                        <GraduationCap size={24} />
                    </div>
                    <h1 className="mt-5 text-2xl font-bold tracking-tight text-slate-950">Reset your password</h1>
                    <p className="mt-2 text-sm leading-6 text-slate-500">
                        Enter your school account email and we will send a secure reset link.
                    </p>
                    {status && (
                        <div role="status" className="mt-5 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{status}</div>
                    )}
                    {form.errors.email && (
                        <div role="alert" className="mt-5 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-800">{form.errors.email}</div>
                    )}
                    <form onSubmit={submit} className="mt-6 space-y-5">
                        <Field label="Email address">
                            <input
                                type="email"
                                autoComplete="email"
                                required
                                value={form.data.email}
                                onChange={(event) => form.setData('email', event.target.value)}
                                className={inputClass(form.errors.email)}
                                placeholder="you@school.edu"
                            />
                        </Field>
                        <button
                            disabled={form.processing}
                            className="flex w-full items-center justify-center gap-2 rounded-xl bg-teal-800 px-4 py-3 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-60"
                        >
                            {form.processing && <LoaderCircle size={17} className="animate-spin" />}
                            Send reset link
                        </button>
                    </form>
                </section>
            </main>
        </>
    );
}
