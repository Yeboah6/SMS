import { Link, Head, usePage } from '@inertiajs/react';
import {
    ArrowUpRight,
    BookOpen,
    CalendarDays,
    ClipboardList,
    GraduationCap,
    ReceiptText,
    UsersRound,
} from 'lucide-react';
import AppLayout from '../Layouts/AppLayout';

const icons = {
    book: BookOpen,
    clipboard: ClipboardList,
    graduation: GraduationCap,
    receipt: ReceiptText,
    users: UsersRound,
};

export default function Dashboard({ dashboard }) {
    const { feeSettings } = usePage().props;

    return (
        <AppLayout
            title="School overview"
            description={`A role-specific overview for ${dashboard.roleLabel}.`}
        >
            <Head title="School overview" />

            <section className="overflow-hidden rounded-3xl bg-gradient-to-br from-teal-800 via-teal-800 to-emerald-700 p-6 text-white shadow-lg shadow-teal-950/10 sm:p-9">
                <div className="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                    <div className="max-w-2xl">
                        <p className="text-sm font-semibold text-teal-100">{dashboard.roleLabel} workspace</p>
                        <h2 className="mt-3 text-2xl font-bold tracking-tight sm:text-3xl">
                            Your school operations at a glance.
                        </h2>
                        <p className="mt-3 max-w-xl text-sm leading-6 text-teal-50/90 sm:text-base">
                            Review the items relevant to your role and jump directly to the next task.
                        </p>
                    </div>
                    <div className="flex items-center gap-2 self-start rounded-xl border border-white/20 bg-white/10 px-3.5 py-2.5 text-sm font-medium text-white sm:self-auto">
                        <CalendarDays aria-hidden="true" size={17} />
                        <span>{dashboard.academicYear || 'No active academic year'}</span>
                    </div>
                </div>
            </section>

            {dashboard.metrics.length > 0 && (
                <section aria-label="Key metrics" className="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {dashboard.metrics.map((metric) => (
                        <MetricCard
                            key={`${metric.label}-${metric.href}`}
                            metric={metric}
                            currencyCode={feeSettings.currency_code}
                        />
                    ))}
                </section>
            )}

            <section className="mt-8 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 className="font-semibold text-slate-900">Quick actions</h2>
                        <p className="mt-1 text-sm text-slate-500">Open a workspace you can access.</p>
                    </div>
                    <span className="text-xs font-medium text-slate-400">{dashboard.actions.length} available</span>
                </div>
                {dashboard.actions.length > 0 ? (
                    <div className="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        {dashboard.actions.map((action) => (
                            <Link
                                key={action.href}
                                href={action.href}
                                className="group flex min-h-14 items-center justify-between gap-3 rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-teal-200 hover:bg-teal-50 hover:text-teal-800"
                            >
                                <span>{action.label}</span>
                                <ArrowUpRight aria-hidden="true" size={16} className="shrink-0 text-slate-400 transition group-hover:text-teal-700" />
                            </Link>
                        ))}
                    </div>
                ) : (
                    <p className="mt-5 rounded-xl bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">
                        No role-specific shortcuts are currently available for this account.
                    </p>
                )}
            </section>
        </AppLayout>
    );
}

function MetricCard({ metric, currencyCode }) {
    const Icon = icons[metric.icon] || ClipboardList;
    const formattedValue = metric.format === 'amount'
        ? new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency: currencyCode,
        }).format(metric.value)
        : metric.value.toLocaleString();

    return (
        <article className="rounded-2xl border border-slate-200 bg-white p-5">
            <div className="flex items-start justify-between">
                <div>
                    <p className="text-sm font-medium text-slate-500">{metric.label}</p>
                    <p className="mt-3 text-2xl font-bold tracking-tight text-slate-950">{formattedValue}</p>
                </div>
                <span className="flex size-10 items-center justify-center rounded-xl bg-teal-50 text-teal-700">
                    <Icon aria-hidden="true" size={19} />
                </span>
            </div>
            <p className="mt-2 text-xs text-slate-500">{metric.detail}</p>
        </article>
    );
}
