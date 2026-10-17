import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { GraduationCap, LoaderCircle } from 'lucide-react';

export default function Login() {
    const { appName, status } = usePage().props;
    const form = useForm({
        email: '',
        password: '',
        remember: false,
    });

    function submit(event) {
        event.preventDefault();
        form.post('/login', {
            onFinish: () => form.reset('password'),
        });
    }

    return (
        <>
            <Head title="Sign in" />
            <main className="flex min-h-screen bg-white">
                <section className="flex w-full items-center justify-center px-5 py-12 sm:px-8 lg:w-[52%]">
                    <div className="w-full max-w-md">
                        <Link href="/" className="mb-10 inline-flex items-center gap-3">
                            <span className="flex size-11 items-center justify-center rounded-2xl bg-teal-700 text-white">
                                <GraduationCap size={23} />
                            </span>
                            <span>
                                <span className="block text-sm font-bold tracking-tight text-slate-950">{appName}</span>
                                <span className="block text-xs text-slate-500">Secure school workspace</span>
                            </span>
                        </Link>
                        <h1 className="text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">Welcome back</h1>
                        <p className="mt-2 text-sm leading-6 text-slate-500">Sign in with your school account to continue.</p>

                        {status && (
                            <div role="status" className="mt-6 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                                {status}
                            </div>
                        )}

                        <form onSubmit={submit} className="mt-8 space-y-5">
                            <Field label="Email address" error={form.errors.email}>
                                <input
                                    autoComplete="username"
                                    autoFocus
                                    type="email"
                                    name="email"
                                    required
                                    value={form.data.email}
                                    onChange={(event) => form.setData('email', event.target.value)}
                                    className={inputClass(form.errors.email)}
                                    placeholder="you@school.edu"
                                />
                            </Field>
                            <Field label="Password" error={form.errors.password}>
                                <input
                                    autoComplete="current-password"
                                    type="password"
                                    name="password"
                                    required
                                    value={form.data.password}
                                    onChange={(event) => form.setData('password', event.target.value)}
                                    className={inputClass(form.errors.password)}
                                    placeholder="Enter your password"
                                />
                            </Field>
                            <div className="flex items-center justify-between gap-3">
                                <label className="flex items-center gap-2 text-sm text-slate-600">
                                    <input
                                        type="checkbox"
                                        checked={form.data.remember}
                                        onChange={(event) => form.setData('remember', event.target.checked)}
                                        className="size-4 rounded border-slate-300 text-teal-700 focus:ring-teal-700"
                                    />
                                    Remember me
                                </label>
                                <Link href="/forgot-password" className="text-sm font-semibold text-teal-800 hover:text-teal-950">
                                    Forgot password?
                                </Link>
                            </div>
                            <button
                                type="submit"
                                disabled={form.processing}
                                className="flex w-full items-center justify-center gap-2 rounded-xl bg-teal-800 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-900 focus:outline-none focus:ring-2 focus:ring-teal-700 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {form.processing && <LoaderCircle size={17} className="animate-spin" />}
                                Sign in
                            </button>
                        </form>
                        <p className="mt-8 text-center text-xs leading-5 text-slate-400">
                            Access is provided by your school administrator.{' '}
                            <Link href="/enquiry" className="font-semibold text-teal-800 hover:text-teal-950">
                                Enquire about admissions
                            </Link>
                        </p>
                    </div>
                </section>
                <aside className="relative hidden flex-1 overflow-hidden bg-gradient-to-br from-teal-950 via-teal-900 to-emerald-800 p-12 lg:flex lg:items-end">
                    <div className="absolute -right-28 -top-24 size-[32rem] rounded-full border border-white/10" />
                    <div className="absolute -right-12 -top-8 size-[22rem] rounded-full border border-white/10" />
                    <div className="relative max-w-lg pb-4">
                        <p className="text-sm font-semibold uppercase tracking-[0.18em] text-teal-200">One school. One workspace.</p>
                        <h2 className="mt-5 text-4xl font-bold leading-tight tracking-tight text-white">
                            The foundation for a more connected school community.
                        </h2>
                        <p className="mt-5 max-w-md text-base leading-7 text-teal-100/80">
                            A secure home for the people and processes that help students thrive.
                        </p>
                    </div>
                </aside>
            </main>
        </>
    );
}

export function Field({ label, error, children }) {
    return (
        <div>
            <label className="mb-2 block text-sm font-medium text-slate-700">
                {label}
                {children}
            </label>
            {error && <p className="mt-1.5 text-sm text-rose-700">{error}</p>}
        </div>
    );
}

export function inputClass(error) {
    return `w-full rounded-xl border bg-white px-3.5 py-3 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-teal-700 focus:ring-4 focus:ring-teal-700/10 ${
        error ? 'border-rose-400' : 'border-slate-200'
    }`;
}
