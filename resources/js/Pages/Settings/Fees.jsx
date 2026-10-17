import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import PageAlerts from '../../Components/Exams/PageAlerts';
import SettingsNav from '../../Components/Settings/SettingsNav';
import SettingsSaveBar from '../../Components/Settings/SettingsSaveBar';
import { Field, inputClass } from '../Auth/Login';

export default function SettingsFees({ settings }) {
    const form = useForm({ fee_settings: settings.fee_settings });

    function update(key, value) {
        form.setData('fee_settings', { ...form.data.fee_settings, [key]: value });
    }

    function submit(event) {
        event.preventDefault();
        form.put('/settings/fees');
    }

    return (
        <AppLayout title="Fee defaults" description="Used for displaying fee amounts and pre-filling new fee due dates.">
            <Head title="Fee defaults" />
            <PageAlerts />
            <SettingsNav current="fees" />

            <form onSubmit={submit} className="grid max-w-4xl gap-6">
                <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
                    <div className="grid gap-5 sm:grid-cols-2">
                        <div>
                            <Field label="Currency code (ISO 4217)" error={form.errors['fee_settings.currency_code']}>
                                <input value={form.data.fee_settings.currency_code} maxLength={3} onChange={(event) => update('currency_code', event.target.value.toUpperCase())} className={inputClass(form.errors['fee_settings.currency_code'])} placeholder="GHS" required />
                            </Field>
                            <p className="mt-1.5 text-xs text-slate-500">Three letters, for example GHS or USD. Changes how every amount is displayed; stored amounts are not converted.</p>
                        </div>
                        <div>
                            <Field label="Default fee due period (days)" error={form.errors['fee_settings.default_due_days']}>
                                <input type="number" min="1" max="365" value={form.data.fee_settings.default_due_days} onChange={(event) => update('default_due_days', event.target.value)} className={inputClass(form.errors['fee_settings.default_due_days'])} required />
                            </Field>
                            <p className="mt-1.5 text-xs text-slate-500">New fees start with a due date this many days from today.</p>
                        </div>
                    </div>
                </section>
                <SettingsSaveBar form={form} label="Save fee defaults" />
            </form>
        </AppLayout>
    );
}