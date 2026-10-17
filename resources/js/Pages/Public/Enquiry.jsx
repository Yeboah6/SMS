import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { GraduationCap } from 'lucide-react';
import { Field, inputClass } from '../Auth/Login';

export default function PublicEnquiry() {
    const { appName, status } = usePage().props;
    const form = useForm({
        parent_name: '',
        parent_phone: '',
        parent_email: '',
        parent_occupation: '',
        parent_address: '',
        child_first_name: '',
        child_middle_name: '',
        child_last_name: '',
        date_of_birth: '',
        gender: 'prefer_not_to_say',
        child_address: '',
        applying_for: '',
    });

    function submit(event) {
        event.preventDefault();
        form.post('/enquiry', { onSuccess: () => form.reset() });
    }

    return (
        <>
            <Head title="Admissions enquiry" />
            <main className="min-h-screen bg-slate-50 px-4 py-10 sm:px-6">
                <div className="mx-auto max-w-3xl">
                    <Link href="/login" className="mb-8 inline-flex items-center gap-3">
                        <span className="flex size-11 items-center justify-center rounded-2xl bg-teal-700 text-white">
                            <GraduationCap size={23} />
                        </span>
                        <span className="text-sm font-bold tracking-tight text-slate-950">{appName}</span>
                    </Link>
                    <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8">
                        <h1 className="text-2xl font-bold tracking-tight text-slate-950">Admissions enquiry</h1>
                        <p className="mt-2 text-sm leading-6 text-slate-500">Share a few details and our admissions team will contact you. This form does not create an account or require document uploads.</p>
                        {status && <div role="status" className="mt-5 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{status}</div>}
                        <form onSubmit={submit} className="mt-6 grid gap-4 sm:grid-cols-2">
                            <Field label="Parent / guardian name" error={form.errors.parent_name}>
                                <input autoComplete="name" value={form.data.parent_name} onChange={(event) => form.setData('parent_name', event.target.value)} className={inputClass(form.errors.parent_name)} required />
                            </Field>
                            <Field label="Parent phone" error={form.errors.parent_phone}>
                                <input type="tel" autoComplete="tel" value={form.data.parent_phone} onChange={(event) => form.setData('parent_phone', event.target.value)} className={inputClass(form.errors.parent_phone)} required />
                            </Field>
                            <Field label="Parent email" error={form.errors.parent_email}>
                                <input type="email" autoComplete="email" value={form.data.parent_email} onChange={(event) => form.setData('parent_email', event.target.value)} className={inputClass(form.errors.parent_email)} />
                            </Field>
                            <Field label="Parent occupation" error={form.errors.parent_occupation}>
                                <input value={form.data.parent_occupation} onChange={(event) => form.setData('parent_occupation', event.target.value)} className={inputClass(form.errors.parent_occupation)} />
                            </Field>
                            <Field label="Child first name" error={form.errors.child_first_name}>
                                <input value={form.data.child_first_name} onChange={(event) => form.setData('child_first_name', event.target.value)} className={inputClass(form.errors.child_first_name)} required />
                            </Field>
                            <Field label="Child middle name" error={form.errors.child_middle_name}>
                                <input value={form.data.child_middle_name} onChange={(event) => form.setData('child_middle_name', event.target.value)} className={inputClass(form.errors.child_middle_name)} />
                            </Field>
                            <Field label="Child last name" error={form.errors.child_last_name}>
                                <input value={form.data.child_last_name} onChange={(event) => form.setData('child_last_name', event.target.value)} className={inputClass(form.errors.child_last_name)} required />
                            </Field>
                            <Field label="Date of birth" error={form.errors.date_of_birth}>
                                <input type="date" max={new Date().toISOString().slice(0, 10)} value={form.data.date_of_birth} onChange={(event) => form.setData('date_of_birth', event.target.value)} className={inputClass(form.errors.date_of_birth)} required />
                            </Field>
                            <Field label="Gender" error={form.errors.gender}>
                                <select value={form.data.gender} onChange={(event) => form.setData('gender', event.target.value)} className={inputClass(form.errors.gender)} required>
                                    <option value="female">Female</option>
                                    <option value="male">Male</option>
                                    <option value="other">Other</option>
                                    <option value="prefer_not_to_say">Prefer not to say</option>
                                </select>
                            </Field>
                            <Field label="Class applying for" error={form.errors.applying_for}>
                                <input value={form.data.applying_for} onChange={(event) => form.setData('applying_for', event.target.value)} className={inputClass(form.errors.applying_for)} placeholder="Class 1" required />
                            </Field>
                            <div className="sm:col-span-2">
                                <Field label="Parent address" error={form.errors.parent_address}>
                                    <textarea rows={2} autoComplete="street-address" value={form.data.parent_address} onChange={(event) => form.setData('parent_address', event.target.value)} className={inputClass(form.errors.parent_address)} />
                                </Field>
                            </div>
                            <div className="sm:col-span-2">
                                <Field label="Child address" error={form.errors.child_address}>
                                    <textarea rows={2} value={form.data.child_address} onChange={(event) => form.setData('child_address', event.target.value)} className={inputClass(form.errors.child_address)} />
                                </Field>
                            </div>
                            <div className="flex flex-wrap items-center gap-4 sm:col-span-2">
                                <button disabled={form.processing} className="rounded-xl bg-teal-800 px-5 py-3 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-60">
                                    {form.processing ? 'Submitting…' : 'Send enquiry'}
                                </button>
                                <Link href="/login" className="text-sm font-semibold text-teal-800 hover:text-teal-950">Staff sign in</Link>
                            </div>
                        </form>
                    </section>
                </div>
            </main>
        </>
    );
}
