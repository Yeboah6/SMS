import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import PageAlerts from '../../Components/Exams/PageAlerts';
import SettingsNav from '../../Components/Settings/SettingsNav';
import SettingsSaveBar from '../../Components/Settings/SettingsSaveBar';

export default function SettingsNotifications({ settings }) {
    const form = useForm({ notification_settings: settings.notification_settings });

    function update(key, value) {
        form.setData('notification_settings', { ...form.data.notification_settings, [key]: value });
    }

    function submit(event) {
        event.preventDefault();
        form.put('/settings/notifications');
    }

    return (
        <AppLayout title="Notifications" description="Choose which channels receive published school notices. Preferences apply to future deliveries.">
            <Head title="Notification settings" />
            <PageAlerts />
            <SettingsNav current="notifications" />

            <form onSubmit={submit} className="grid max-w-4xl gap-6">
                <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
                    <div className="grid gap-3 sm:grid-cols-2">
                        <Toggle label="In-app notification inbox" description="Save notice alerts and read receipts in each user's inbox." checked={form.data.notification_settings.database_notices} onChange={(value) => update('database_notices', value)} error={form.errors['notification_settings.database_notices']} />
                        <Toggle label="Email notifications" description="Queue an email when a notice is published." checked={form.data.notification_settings.email_notices} onChange={(value) => update('email_notices', value)} error={form.errors['notification_settings.email_notices']} />
                    </div>
                </section>
                <SettingsSaveBar form={form} label="Save notification settings" />
            </form>
        </AppLayout>
    );
}

function Toggle({ label, description, checked, onChange, error }) {
    return (
        <label className="flex items-start gap-3 rounded-xl border border-slate-200 p-4">
            <input type="checkbox" checked={checked} onChange={(event) => onChange(event.target.checked)} className="mt-1 size-4 rounded border-slate-300 text-teal-700 focus:ring-teal-700" />
            <span>
                <span className="block text-sm font-medium text-slate-800">{label}</span>
                <span className="mt-1 block text-xs leading-5 text-slate-500">{description}</span>
                {error && <span role="alert" className="mt-1 block text-xs text-rose-700">{error}</span>}
            </span>
        </label>
    );
}