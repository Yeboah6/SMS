import { Head, useForm, usePage } from '@inertiajs/react';
import { Field, inputClass } from '../Auth/Login';
import AppLayout from '../../Layouts/AppLayout';

export default function SettingsEdit({ settings }) {
    const { status } = usePage().props;
    const form = useForm({
        school_name: settings?.school_name || '',
        motto: settings?.motto || '',
        address: settings?.address || '',
        phone: settings?.phone || '',
        email: settings?.email || '',
        website: settings?.website || '',
        attendance_threshold: settings?.attendance_threshold || '75.00',
    });

    function submit(event) {
        event.preventDefault();
        form.put('/settings');
    }

    return (
        <AppLayout title="School settings" description="Set the school's identity and foundational academic defaults.">
            <Head title="School settings" />
            {status && <div role="status" className="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{status}</div>}
            <section className="max-w-4xl rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
                <h2 className="font-semibold text-slate-900">School identity</h2>
                <p className="mt-1 text-sm text-slate-500">These details are used in the application header and will be reused in school documents.</p>
                <form onSubmit={submit} className="mt-6 grid gap-5 sm:grid-cols-2">
                    <Field label="School name" error={form.errors.school_name}>
                        <input value={form.data.school_name} onChange={(event) => form.setData('school_name', event.target.value)} className={inputClass(form.errors.school_name)} required />
                    </Field>
                    <Field label="Motto" error={form.errors.motto}>
                        <input value={form.data.motto} onChange={(event) => form.setData('motto', event.target.value)} className={inputClass(form.errors.motto)} />
                    </Field>
                    <Field label="Phone" error={form.errors.phone}>
                        <input type="tel" value={form.data.phone} onChange={(event) => form.setData('phone', event.target.value)} className={inputClass(form.errors.phone)} autoComplete="tel" />
                    </Field>
                    <Field label="Email" error={form.errors.email}>
                        <input type="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} className={inputClass(form.errors.email)} autoComplete="email" />
                    </Field>
                    <Field label="Website" error={form.errors.website}>
                        <input type="url" value={form.data.website} onChange={(event) => form.setData('website', event.target.value)} className={inputClass(form.errors.website)} placeholder="https://" />
                    </Field>
                    <Field label="Low-attendance threshold (%)" error={form.errors.attendance_threshold}>
                        <input
                            type="number"
                            min="0"
                            max="100"
                            step="0.01"
                            value={form.data.attendance_threshold}
                            onChange={(event) => form.setData('attendance_threshold', event.target.value)}
                            className={inputClass(form.errors.attendance_threshold)}
                            required
                        />
                    </Field>
                    <div className="sm:col-span-2">
                        <Field label="Address" error={form.errors.address}>
                            <textarea
                                rows={3}
                                value={form.data.address}
                                onChange={(event) => form.setData('address', event.target.value)}
                                className={inputClass(form.errors.address)}
                                autoComplete="street-address"
                            />
                        </Field>
                    </div>
                    <div className="sm:col-span-2">
                        <button disabled={form.processing} className="rounded-xl bg-teal-800 px-5 py-3 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-60">
                            Save school settings
                        </button>
                    </div>
                </form>
            </section>
        </AppLayout>
    );
}
