import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Eye, EyeOff, GraduationCap, LoaderCircle } from 'lucide-react';
import { Children, cloneElement, isValidElement, useId, useState } from 'react';
import PageAlerts from '../../Components/Exams/PageAlerts';

export default function Login() {
    const { appName, schoolSettings, schoolLogoUrl } = usePage().props;
    const schoolName = schoolSettings?.school_name || appName;
    const [showPassword, setShowPassword] = useState(false);
    const form = useForm({
        login: '',
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
            <PageAlerts />
            <main className="flex min-h-screen bg-white">
                <section className="flex w-full items-center justify-center px-5 py-12 sm:px-8 lg:w-[52%]">
                    <div className="w-full max-w-md">
                        <Link href="/" className="mb-10 inline-flex items-center gap-3">
                            <span className="flex size-11 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-teal-700 text-white">
                                {schoolLogoUrl ? (
                                    <img src={schoolLogoUrl} alt="" className="size-full bg-white object-contain p-1" />
                                ) : (
                                    <GraduationCap size={23} />
                                )}
                            </span>
                            <span>
                                <span className="block text-sm font-bold tracking-tight text-slate-950">{schoolName}</span>
                                <span className="block text-xs text-slate-500">Secure school workspace</span>
                            </span>
                        </Link>
                        <h1 className="text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">Welcome back</h1>
                        <p className="mt-2 text-sm leading-6 text-slate-500">Sign in with your school account to continue.</p>

                        <form onSubmit={submit} className="mt-8 space-y-5">
                            <Field label="Email or admission number" error={form.errors.login}>
                                <input
                                    autoComplete="username"
                                    autoFocus
                                    type="text"
                                    name="login"
                                    required
                                    value={form.data.login}
                                    onChange={(event) => form.setData('login', event.target.value)}
                                    className={inputClass(form.errors.login)}
                                    placeholder="you@school.edu or ADM0001"
                                />
                            </Field>
                            <Field label="Password" error={form.errors.password}>
                                <div className="relative">
                                    <input
                                        autoComplete="current-password"
                                        type={showPassword ? 'text' : 'password'}
                                        name="password"
                                        required
                                        value={form.data.password}
                                        onChange={(event) => form.setData('password', event.target.value)}
                                        className={`${inputClass(form.errors.password)} pr-11`}
                                        placeholder="Enter your password"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setShowPassword((visible) => !visible)}
                                        aria-label={showPassword ? 'Hide password' : 'Show password'}
                                        aria-pressed={showPassword}
                                        className="absolute inset-y-0 right-0 flex items-center rounded-r-xl px-3.5 text-slate-400 transition hover:text-slate-600 focus:outline-none focus-visible:text-teal-700"
                                    >
                                        {showPassword ? <EyeOff size={17} /> : <Eye size={17} />}
                                    </button>
                                </div>
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
                        {schoolLogoUrl && (
                            <img src={schoolLogoUrl} alt="" className="mb-6 size-20 rounded-2xl bg-white object-contain p-2" />
                        )}
                        <h2 className="text-4xl font-bold leading-tight tracking-tight text-white">{schoolName}</h2>
                    </div>
                </aside>
            </main>
        </>
    );
}

export function Field({ label, error, children }) {
    const errorId = useId();
    const describedChildren = error
        ? Children.map(children, (child) => addErrorDescription(child, errorId))
        : children;

    return (
        <div>
            <label className="mb-2 block text-sm font-medium text-slate-700">
                {label}
                {describedChildren}
            </label>
            {error && <p id={errorId} role="alert" className="mt-1.5 text-sm text-rose-700">{error}</p>}
        </div>
    );
}

export function PasswordInput({ autoComplete, error, errorId, onChange, placeholder, required, value }) {
    const [showPassword, setShowPassword] = useState(false);

    return (
        <div className="relative">
            <input
                autoComplete={autoComplete}
                type={showPassword ? 'text' : 'password'}
                required={required}
                value={value}
                onChange={onChange}
                aria-describedby={errorId}
                aria-invalid={Boolean(error)}
                className={`${inputClass(error)} pr-11`}
                placeholder={placeholder}
            />
            <button
                type="button"
                onClick={() => setShowPassword((visible) => !visible)}
                aria-label={showPassword ? 'Hide password' : 'Show password'}
                aria-pressed={showPassword}
                className="absolute inset-y-0 right-0 flex items-center rounded-r-xl px-3.5 text-slate-400 transition hover:text-slate-600 focus:outline-none focus-visible:text-teal-700"
            >
                {showPassword ? <EyeOff size={17} /> : <Eye size={17} />}
            </button>
        </div>
    );
}

function addErrorDescription(child, errorId) {
    if (!isValidElement(child)) {
        return child;
    }

    if (typeof child.type === 'string' && ['input', 'select', 'textarea'].includes(child.type)) {
        const describedBy = [child.props['aria-describedby'], errorId].filter(Boolean).join(' ');

        return cloneElement(child, {
            'aria-describedby': describedBy,
            'aria-invalid': true,
        });
    }

    if (child.type === PasswordInput) {
        return cloneElement(child, { errorId });
    }

    if (child.props.children) {
        return cloneElement(child, {
            children: Children.map(child.props.children, (nestedChild) => addErrorDescription(nestedChild, errorId)),
        });
    }

    return child;
}

export function inputClass(error) {
    return `w-full rounded-xl border bg-white px-3.5 py-3 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-teal-600 focus:ring-4 focus:ring-teal-700/10 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-500 ${
        error ? 'border-rose-400 focus:border-rose-500 focus:ring-rose-500/10' : 'border-slate-200'
    }`;
}