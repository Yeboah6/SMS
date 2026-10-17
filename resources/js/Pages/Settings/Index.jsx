import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import PageAlerts from '../../Components/Exams/PageAlerts';
import SettingsNav from '../../Components/Settings/SettingsNav';

export default function SettingsIndex({ settings, configured, can }) {
    const fee = settings.fee_settings;
    const notifications = settings.notification_settings;

    return (
        <AppLayout title="School settings" description="School identity, grading, fees and notification defaults.">
            <Head title="School settings" />
            <PageAlerts />
            <SettingsNav current="overview" canUpdate={can.update} />

            {!configured && (
                <div role="status" className="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    The school has not been set up yet. {can.update ? <Link href="/settings/school" className="font-semibold underline">Start with the school profile</Link> : 'Ask an administrator to complete the school profile.'}
                </div>
            )}

            <div className="grid gap-6 xl:grid-cols-2">
                <Card title="School profile" description="Shown in the app and on school documents." href="/settings/school" canUpdate={can.update}>
                    <div className="flex items-start gap-4">
                        {settings.logo_url && <img src={settings.logo_url} alt="School logo" className="size-16 shrink-0 rounded-xl border border-slate-200 bg-white object-contain p-1" />}
                        <dl className="grid flex-1 gap-3 text-sm sm:grid-cols-2">
                            <Row label="School name" value={settings.school_name} />
                            <Row label="Motto" value={settings.motto} />
                            <Row label="Phone" value={settings.phone} />
                            <Row label="Email" value={settings.email} />
                            <Row label="Website" value={settings.website} />
                            <div className="sm:col-span-2"><Row label="Address" value={settings.address} /></div>
                        </dl>
                    </div>
                </Card>

                <Card title="Grading & attendance" description="How percentages become grades, and when attendance is flagged." href="/settings/grading" canUpdate={can.update} note={settings.uses_default_grades ? 'Using the built-in default scale.' : null}>
                    <div className="flex flex-wrap gap-2">
                        {settings.grade_settings.bands.map((band) => (
                            <span key={band.label} className="rounded-lg bg-slate-100 px-3 py-1.5 text-sm font-medium text-slate-700">{band.label} <span className="text-slate-500">≥ {Number(band.minimum_percentage)}%</span></span>
                        ))}
                    </div>
                    <p className="mt-4 text-sm text-slate-600">Low-attendance threshold: <span className="font-semibold text-slate-900">{Number(settings.attendance_threshold)}%</span></p>
                </Card>

                <Card title="Fee defaults" description="Currency used everywhere amounts are shown, and the default due period for new fees." href="/settings/fees" canUpdate={can.update} note={settings.uses_default_fees ? 'Using the built-in defaults.' : null}>
                    <dl className="grid gap-3 text-sm sm:grid-cols-2">
                        <Row label="Currency" value={fee.currency_code} />
                        <Row label="Default due period" value={`${fee.default_due_days} days`} />
                    </dl>
                </Card>

                <Card title="Notifications" description="Which channels receive published school notices." href="/settings/notifications" canUpdate={can.update} note={settings.uses_default_notifications ? 'Using the built-in defaults.' : null}>
                    <ul className="grid gap-2 text-sm">
                        <State label="In-app notification inbox" on={notifications.database_notices} />
                        <State label="Email notifications" on={notifications.email_notices} />
                    </ul>
                </Card>
            </div>
        </AppLayout>
    );
}

function Card({ title, description, href, canUpdate, note = null, children }) {
    return (
        <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <h2 className="font-semibold text-slate-900">{title}</h2>
                    <p className="mt-1 text-sm text-slate-500">{description}</p>
                </div>
                {canUpdate && <Link href={href} className="shrink-0 text-sm font-semibold text-teal-800 hover:text-teal-950">Edit</Link>}
            </div>
            <div className="mt-5">{children}</div>
            {note && <p className="mt-4 text-xs text-slate-500">{note}</p>}
        </section>
    );
}

function Row({ label, value }) {
    return (
        <div>
            <dt className="text-xs font-medium text-slate-500">{label}</dt>
            <dd className="mt-1 break-words text-slate-900">{value || '—'}</dd>
        </div>
    );
}

function State({ label, on }) {
    return (
        <li className="flex items-center justify-between gap-3 rounded-xl border border-slate-100 px-3 py-2.5">
            <span className="text-slate-700">{label}</span>
            <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${on ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-600'}`}>{on ? 'On' : 'Off'}</span>
        </li>
    );
}