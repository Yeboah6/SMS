import { ArrowUpRight, CheckCircle2, Database, LockKeyhole, Users } from 'lucide-react';
import { usePage } from '@inertiajs/react';
import AppLayout from '../Layouts/AppLayout';

export default function Dashboard({ accountCount }) {
    const { activeAcademicYear } = usePage().props;

    return (
        <AppLayout
            title="Welcome to your school workspace"
            description="Your secure application foundation is up and ready for the next setup steps."
        >
            <div className="mb-7 rounded-3xl bg-gradient-to-br from-teal-800 via-teal-800 to-emerald-700 p-6 text-white shadow-lg shadow-teal-950/10 sm:p-9">
                <div className="max-w-2xl">
                    <p className="text-sm font-semibold text-teal-100">Phase 1 · Project foundation</p>
                    <h2 className="mt-3 text-2xl font-bold tracking-tight sm:text-3xl">
                        A better way to run your school starts here.
                    </h2>
                    <p className="mt-3 max-w-xl text-sm leading-6 text-teal-50/90 sm:text-base">
                        Your workspace is ready with secure sign-in, a responsive interface, and a Laravel-powered foundation.
                        School operations will be introduced in sequenced releases.
                    </p>
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    label="User accounts"
                    value={accountCount.toLocaleString()}
                    note="Accounts in this workspace"
                    icon={Users}
                    tone="teal"
                />
                <StatCard
                    label="Authentication"
                    value="Protected"
                    note="Session-based sign-in"
                    icon={LockKeyhole}
                    tone="blue"
                />
                <StatCard
                    label="Application"
                    value="Operational"
                    note="Laravel 13 · Inertia · React"
                    icon={CheckCircle2}
                    tone="green"
                />
                <StatCard
                    label="Academic year"
                    value={activeAcademicYear?.name || 'Not set'}
                    note={activeAcademicYear ? 'Current academic year' : 'Configure in Academic years'}
                    icon={Database}
                    tone="amber"
                />
            </div>

            <section className="mt-8 overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div className="flex flex-col gap-2 border-b border-slate-100 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <div>
                        <h2 className="font-semibold text-slate-900">Foundation checklist</h2>
                        <p className="mt-1 text-sm text-slate-500">Core platform services configured for this release.</p>
                    </div>
                    <span className="inline-flex w-fit items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                        <CheckCircle2 size={14} />
                        Phase 1
                    </span>
                </div>
                <div className="divide-y divide-slate-100">
                    <ChecklistRow
                        title="Secure authentication"
                        detail="Sign-in, sign-out, password reset, and request throttling"
                    />
                    <ChecklistRow
                        title="Application shell"
                        detail="Responsive navigation, user menu, and accessible page layout"
                    />
                    <ChecklistRow
                        title="Database foundation"
                        detail="MySQL connection, user accounts, sessions, cache, and queue tables"
                    />
                    <ChecklistRow
                        title="Next: school setup"
                        detail="School settings, academic years, and role permissions are planned for upcoming phases"
                        pending
                    />
                </div>
            </section>

            <p className="mt-6 flex items-start gap-2 text-xs leading-5 text-slate-500">
                <ArrowUpRight size={15} className="mt-0.5 shrink-0" />
                No school, academic-year, student, or financial data has been invented or seeded into this workspace.
            </p>
        </AppLayout>
    );
}

function StatCard({ label, value, note, icon: Icon, tone }) {
    const tones = {
        teal: 'bg-teal-50 text-teal-700',
        blue: 'bg-blue-50 text-blue-700',
        green: 'bg-emerald-50 text-emerald-700',
        amber: 'bg-amber-50 text-amber-700',
    };

    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5">
            <div className="flex items-start justify-between">
                <div>
                    <p className="text-sm font-medium text-slate-500">{label}</p>
                    <p className="mt-3 text-2xl font-bold tracking-tight text-slate-950">{value}</p>
                </div>
                <span className={`flex size-10 items-center justify-center rounded-xl ${tones[tone]}`}>
                    <Icon size={19} />
                </span>
            </div>
            <p className="mt-2 text-xs text-slate-500">{note}</p>
        </div>
    );
}

function ChecklistRow({ title, detail, pending = false }) {
    return (
        <div className="flex items-start gap-3 px-5 py-4 sm:px-6">
            <span
                className={`mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full ${
                    pending ? 'bg-slate-100 text-slate-400' : 'bg-emerald-50 text-emerald-700'
                }`}
            >
                {pending ? <ArrowUpRight size={13} /> : <CheckCircle2 size={15} />}
            </span>
            <div>
                <p className="text-sm font-medium text-slate-800">{title}</p>
                <p className="mt-1 text-sm leading-5 text-slate-500">{detail}</p>
            </div>
        </div>
    );
}
