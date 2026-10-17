import { Head, useForm, usePage } from '@inertiajs/react';
import { useRef } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import { Field, inputClass } from '../Auth/Login';

export default function SettingsEdit({ settings }) {
    const { status } = usePage().props;
    const logoInput = useRef(null);
    const form = useForm({
        school_name: settings.school_name,
        motto: settings.motto || '',
        address: settings.address || '',
        phone: settings.phone || '',
        email: settings.email || '',
        website: settings.website || '',
        attendance_threshold: settings.attendance_threshold,
        grade_settings: settings.grade_settings,
        fee_settings: settings.fee_settings,
        notification_settings: settings.notification_settings,
        logo: null,
        remove_logo: false,
    });

    function submit(event) {
        event.preventDefault();
        form.put('/settings');
    }

    function updateGradeBand(index, field, value) {
        const bands = form.data.grade_settings.bands.map((band, bandIndex) => (
            bandIndex === index ? { ...band, [field]: value } : band
        ));
        form.setData('grade_settings', { bands });
    }

    function moveGradeBand(index, direction) {
        const bands = [...form.data.grade_settings.bands];
        const nextIndex = index + direction;
        [bands[index], bands[nextIndex]] = [bands[nextIndex], bands[index]];
        form.setData('grade_settings', { bands });
    }

    return (
        <AppLayout title="School settings" description="Configure school identity, grading, fees, and notification defaults.">
            <Head title="School settings" />
            {status && <div role="status" className="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{status}</div>}
            <form onSubmit={submit} className="grid gap-6">
                <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
                    <h2 className="font-semibold text-slate-900">School identity</h2>
                    <p className="mt-1 text-sm text-slate-500">These details appear in the application and can be reused in school documents.</p>
                    <div className="mt-6 grid gap-5 sm:grid-cols-2">
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
                            <Field label="School logo (JPG, PNG, or WebP; maximum 2 MB)" error={form.errors.logo}>
                                <input
                                    ref={logoInput}
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    onChange={(event) => {
                                        form.setData('logo', event.target.files?.[0] || null);
                                        form.setData('remove_logo', false);
                                    }}
                                    className={inputClass(form.errors.logo)}
                                />
                            </Field>
                            {settings.logo_url && !form.data.remove_logo && (
                                <div className="mt-3 flex items-center gap-4">
                                    <img src={settings.logo_url} alt="Current school logo" className="size-16 rounded-xl border border-slate-200 bg-white object-contain p-1" />
                                    <button
                                        type="button"
                                        onClick={() => {
                                            form.setData('logo', null);
                                            form.setData('remove_logo', true);
                                            if (logoInput.current) {
                                                logoInput.current.value = '';
                                            }
                                        }}
                                        className="text-sm font-semibold text-rose-700 hover:text-rose-900"
                                    >
                                        Remove current logo
                                    </button>
                                </div>
                            )}
                            {form.data.logo && <p className="mt-2 text-xs text-slate-500">Selected: {form.data.logo.name}</p>}
                            {form.data.remove_logo && <p className="mt-2 text-xs text-slate-500">The current logo will be removed when settings are saved.</p>}
                        </div>
                    </div>
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
                    <div className="flex flex-col gap-1">
                        <h2 className="font-semibold text-slate-900">Grade scale</h2>
                        <p className="text-sm text-slate-500">Set the minimum percentage for each grade, ordered from highest to lowest. The final band must begin at 0%.</p>
                    </div>
                    <div className="mt-5 grid gap-3">
                        {form.data.grade_settings.bands.map((band, index) => (
                            <div key={`${index}-${band.label}`} className="grid gap-3 rounded-xl border border-slate-200 p-4 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] sm:items-end">
                                <Field label="Grade label" error={form.errors[`grade_settings.bands.${index}.label`]}>
                                    <input
                                        value={band.label}
                                        maxLength={10}
                                        onChange={(event) => updateGradeBand(index, 'label', event.target.value)}
                                        className={inputClass(form.errors[`grade_settings.bands.${index}.label`])}
                                        required
                                    />
                                </Field>
                                <Field label="Minimum percentage" error={form.errors[`grade_settings.bands.${index}.minimum_percentage`]}>
                                    <input
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        value={band.minimum_percentage}
                                        onChange={(event) => updateGradeBand(index, 'minimum_percentage', event.target.value)}
                                        className={inputClass(form.errors[`grade_settings.bands.${index}.minimum_percentage`])}
                                        required
                                    />
                                </Field>
                                <div className="flex gap-2">
                                    <button
                                        type="button"
                                        disabled={index === 0}
                                        onClick={() => moveGradeBand(index, -1)}
                                        className="rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 disabled:opacity-40"
                                        aria-label={`Move grade ${band.label} up`}
                                    >
                                        Up
                                    </button>
                                    <button
                                        type="button"
                                        disabled={index === form.data.grade_settings.bands.length - 1 || form.data.grade_settings.bands.length <= 2}
                                        onClick={() => moveGradeBand(index, 1)}
                                        className="rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 disabled:opacity-40"
                                        aria-label={`Move grade ${band.label} down`}
                                    >
                                        Down
                                    </button>
                                    <button
                                        type="button"
                                        disabled={form.data.grade_settings.bands.length <= 2}
                                        onClick={() => form.setData('grade_settings', {
                                            bands: form.data.grade_settings.bands.filter((_, bandIndex) => bandIndex !== index),
                                        })}
                                        className="rounded-lg border border-rose-200 px-3 py-2 text-sm font-medium text-rose-700 disabled:opacity-40"
                                        aria-label={`Remove grade ${band.label}`}
                                    >
                                        Remove
                                    </button>
                                </div>
                            </div>
                        ))}
                        {form.errors['grade_settings.bands'] && <p role="alert" className="text-sm text-rose-700">{form.errors['grade_settings.bands']}</p>}
                        <button
                            type="button"
                            disabled={form.data.grade_settings.bands.length >= 12}
                            onClick={() => form.setData('grade_settings', {
                                bands: [...form.data.grade_settings.bands, { label: '', minimum_percentage: '' }],
                            })}
                            className="w-fit rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50"
                        >
                            Add grade band
                        </button>
                    </div>
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
                    <h2 className="font-semibold text-slate-900">Fee defaults</h2>
                    <p className="mt-1 text-sm text-slate-500">Used for displaying fee amounts and pre-filling new fee due dates.</p>
                    <div className="mt-5 grid gap-5 sm:grid-cols-2">
                        <Field label="Currency code (ISO 4217)" error={form.errors['fee_settings.currency_code']}>
                            <input
                                value={form.data.fee_settings.currency_code}
                                maxLength={3}
                                onChange={(event) => form.setData('fee_settings', {
                                    ...form.data.fee_settings,
                                    currency_code: event.target.value.toUpperCase(),
                                })}
                                className={inputClass(form.errors['fee_settings.currency_code'])}
                                placeholder="USD"
                                required
                            />
                        </Field>
                        <Field label="Default fee due period (days)" error={form.errors['fee_settings.default_due_days']}>
                            <input
                                type="number"
                                min="1"
                                max="365"
                                value={form.data.fee_settings.default_due_days}
                                onChange={(event) => form.setData('fee_settings', {
                                    ...form.data.fee_settings,
                                    default_due_days: event.target.value,
                                })}
                                className={inputClass(form.errors['fee_settings.default_due_days'])}
                                required
                            />
                        </Field>
                    </div>
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
                    <h2 className="font-semibold text-slate-900">Notice notifications</h2>
                    <p className="mt-1 text-sm text-slate-500">Choose which channels receive published school notices. Preferences apply to future deliveries.</p>
                    <div className="mt-5 grid gap-3 sm:grid-cols-2">
                        <Toggle
                            label="In-app notification inbox"
                            description="Save notice alerts and read receipts in each user's inbox."
                            checked={form.data.notification_settings.database_notices}
                            onChange={(value) => form.setData('notification_settings', {
                                ...form.data.notification_settings,
                                database_notices: value,
                            })}
                            error={form.errors['notification_settings.database_notices']}
                        />
                        <Toggle
                            label="Email notifications"
                            description="Queue an email when a notice is published."
                            checked={form.data.notification_settings.email_notices}
                            onChange={(value) => form.setData('notification_settings', {
                                ...form.data.notification_settings,
                                email_notices: value,
                            })}
                            error={form.errors['notification_settings.email_notices']}
                        />
                    </div>
                </section>

                {Object.keys(form.errors).length > 0 && (
                    <p role="alert" className="text-sm text-rose-700">Review the highlighted settings before saving.</p>
                )}
                <div>
                    <button disabled={form.processing} className="rounded-xl bg-teal-800 px-5 py-3 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-60">
                        {form.processing ? 'Saving…' : 'Save school settings'}
                    </button>
                </div>
            </form>
        </AppLayout>
    );
}

function Toggle({ label, description, checked, onChange, error }) {
    return (
        <label className="flex items-start gap-3 rounded-xl border border-slate-200 p-4">
            <input
                type="checkbox"
                checked={checked}
                onChange={(event) => onChange(event.target.checked)}
                className="mt-1 size-4 rounded border-slate-300 text-teal-700 focus:ring-teal-700"
            />
            <span>
                <span className="block text-sm font-medium text-slate-800">{label}</span>
                <span className="mt-1 block text-xs leading-5 text-slate-500">{description}</span>
                {error && <span role="alert" className="mt-1 block text-xs text-rose-700">{error}</span>}
            </span>
        </label>
    );
}
