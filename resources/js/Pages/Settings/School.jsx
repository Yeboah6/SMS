import { Head, useForm } from '@inertiajs/react';
import { useRef } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import PageAlerts from '../../Components/Exams/PageAlerts';
import SettingsNav from '../../Components/Settings/SettingsNav';
import SettingsSaveBar from '../../Components/Settings/SettingsSaveBar';
import { Field, inputClass } from '../Auth/Login';

export default function SettingsSchool({ settings, configured }) {
    const logoInput = useRef(null);
    const form = useForm({
        school_name: settings.school_name,
        motto: settings.motto || '',
        address: settings.address || '',
        phone: settings.phone || '',
        email: settings.email || '',
        website: settings.website || '',
        logo: null,
        remove_logo: false,
    });
    const set = (key) => (event) => form.setData(key, event.target.value);

    function submit(event) {
        event.preventDefault();
        form.put('/settings/school');
    }

    return (
        <AppLayout title="School profile" description="These details appear in the application and can be reused in school documents.">
            <Head title="School profile" />
            <PageAlerts />
            <SettingsNav current="school" />

            {!configured && <p role="status" className="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">Set up the school profile first. The other settings become available once it is saved.</p>}

            <form onSubmit={submit} className="grid max-w-4xl gap-6">
                <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
                    <div className="grid gap-5 sm:grid-cols-2">
                        <Field label="School name" error={form.errors.school_name}>
                            <input value={form.data.school_name} onChange={set('school_name')} className={inputClass(form.errors.school_name)} required />
                        </Field>
                        <Field label="Motto" error={form.errors.motto}>
                            <input value={form.data.motto} onChange={set('motto')} className={inputClass(form.errors.motto)} />
                        </Field>
                        <Field label="Phone" error={form.errors.phone}>
                            <input type="tel" value={form.data.phone} onChange={set('phone')} className={inputClass(form.errors.phone)} autoComplete="tel" />
                        </Field>
                        <Field label="Email" error={form.errors.email}>
                            <input type="email" value={form.data.email} onChange={set('email')} className={inputClass(form.errors.email)} autoComplete="email" />
                        </Field>
                        <div className="sm:col-span-2">
                            <Field label="Website" error={form.errors.website}>
                                <input type="url" value={form.data.website} onChange={set('website')} className={inputClass(form.errors.website)} placeholder="https://" />
                            </Field>
                        </div>
                        <div className="sm:col-span-2">
                            <Field label="Address" error={form.errors.address}>
                                <textarea rows={3} value={form.data.address} onChange={set('address')} className={inputClass(form.errors.address)} autoComplete="street-address" />
                            </Field>
                        </div>
                        <div className="sm:col-span-2">
                            <Field label="School logo (JPG, PNG, or WebP; maximum 2 MB)" error={form.errors.logo}>
                                <input
                                    ref={logoInput}
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    onChange={(event) => {
                                        form.setData({ ...form.data, logo: event.target.files?.[0] || null, remove_logo: false });
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
                                            form.setData({ ...form.data, logo: null, remove_logo: true });
                                            if (logoInput.current) logoInput.current.value = '';
                                        }}
                                        className="text-sm font-semibold text-rose-700 hover:text-rose-900"
                                    >
                                        Remove current logo
                                    </button>
                                </div>
                            )}
                            {form.data.logo && <p className="mt-2 text-xs text-slate-500">Selected: {form.data.logo.name}</p>}
                            {form.data.remove_logo && <p className="mt-2 text-xs text-slate-500">The current logo will be removed when you save.</p>}
                        </div>
                    </div>
                </section>
                <SettingsSaveBar form={form} label="Save school profile" />
            </form>
        </AppLayout>
    );
}