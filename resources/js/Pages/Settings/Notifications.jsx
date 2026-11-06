import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import PageAlerts from '../../Components/Exams/PageAlerts';
import SettingsNav from '../../Components/Settings/SettingsNav';
import SettingsSaveBar from '../../Components/Settings/SettingsSaveBar';
import { Field, inputClass } from '../Auth/Login';

export default function SettingsNotifications({ settings, smsGatewayConfigured }) {
    const form = useForm({ notification_settings: settings.notification_settings });

    function update(key, value) {
        form.setData('notification_settings', { ...form.data.notification_settings, [key]: value });
    }

    function submit(event) {
        event.preventDefault();
        form.put('/settings/notifications');
    }

    return (
        <AppLayout title="Notifications" description="Configure notice delivery channels and customize email and SMS message templates.">
            <Head title="Notification settings" />
            <PageAlerts />
            <SettingsNav current="notifications" />

            <form onSubmit={submit} className="grid max-w-4xl gap-6">
                <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
                    <div className="grid gap-3 sm:grid-cols-2">
                        <Toggle label="In-app notification inbox" description="Save notice alerts and read receipts in each user's inbox." checked={form.data.notification_settings.database_notices} onChange={(value) => update('database_notices', value)} error={form.errors['notification_settings.database_notices']} />
                        <Toggle label="Email notifications" description="Queue an email when a notice is published." checked={form.data.notification_settings.email_notices} onChange={(value) => update('email_notices', value)} error={form.errors['notification_settings.email_notices']} />
                        <Toggle label="SMS notifications" description="Send published notice texts to linked staff and guardian phone numbers." checked={form.data.notification_settings.sms_notices} onChange={(value) => update('sms_notices', value)} error={form.errors['notification_settings.sms_notices']} />
                    </div>
                    <p role="status" className={`mt-4 rounded-xl border px-4 py-3 text-sm ${smsGatewayConfigured ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-900'}`}>
                        {smsGatewayConfigured
                            ? 'Twilio is configured. SMS delivery uses server-side credentials.'
                            : 'Twilio is not configured. Set TWILIO_ACCOUNT_SID and TWILIO_AUTH_TOKEN, then set exactly one of TWILIO_FROM_NUMBER or TWILIO_MESSAGING_SERVICE_SID in the server environment before enabling SMS.'}
                    </p>
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
                    <div className="mb-5">
                        <h2 className="font-semibold text-slate-900">Notice message templates</h2>
                        <p className="mt-1 text-sm text-slate-500">These templates apply to future published notices. Available placeholders: {'{title}'}, {'{body}'}, {'{url}'}, and {'{audience}'}.</p>
                    </div>
                    <div className="grid gap-5">
                        <Field label="Email subject" error={form.errors['notification_settings.notice_email_subject']}>
                            <input
                                value={form.data.notification_settings.notice_email_subject}
                                onChange={(event) => update('notice_email_subject', event.target.value)}
                                className={inputClass(form.errors['notification_settings.notice_email_subject'])}
                                maxLength={255}
                                required
                            />
                        </Field>
                        <Field label="Email body" error={form.errors['notification_settings.notice_email_body']}>
                            <textarea
                                rows={6}
                                value={form.data.notification_settings.notice_email_body}
                                onChange={(event) => update('notice_email_body', event.target.value)}
                                className={inputClass(form.errors['notification_settings.notice_email_body'])}
                                maxLength={5000}
                                required
                            />
                        </Field>
                        <Field label="SMS body" error={form.errors['notification_settings.notice_sms_body']}>
                            <textarea
                                rows={4}
                                value={form.data.notification_settings.notice_sms_body}
                                onChange={(event) => update('notice_sms_body', event.target.value)}
                                className={inputClass(form.errors['notification_settings.notice_sms_body'])}
                                maxLength={1600}
                                required
                            />
                        </Field>
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