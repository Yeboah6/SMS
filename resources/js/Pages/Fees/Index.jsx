import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import PageAlerts from '../../Components/Exams/PageAlerts';
import FeesNav from '../../Components/Fees/FeesNav';
import YearSelect from '../../Components/Fees/YearSelect';
import { AlertTriangle, CalendarDays, CircleDollarSign, ReceiptText, TrendingUp } from 'lucide-react';
import { useMoney } from '../../lib/fees';

export default function FeesOverview({ academicYears, selectedAcademicYear, steps, summary, overdueCount }) {
    const money = useMoney();
    const yearId = selectedAcademicYear?.id;
    const locked = selectedAcademicYear?.status === 'locked';

    return (
        <AppLayout title="Fees & payments" description="Set up charges, price each class, bill students and collect payments, in that order.">
            <Head title="Fees & payments" />
            <PageAlerts />
            <FeesNav current="overview" yearId={yearId} />

            <section className="mb-6 flex flex-wrap items-end justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div>
                    <p className="mb-1 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-400">
                        <CalendarDays size={14} aria-hidden="true" />
                        Selected academic year
                    </p>
                    <div className="flex flex-wrap items-center gap-2">
                        <h2 className="font-semibold text-slate-900">{selectedAcademicYear ? selectedAcademicYear.name : 'No academic year'}</h2>
                        {selectedAcademicYear && (
                            <span className={`rounded-full px-2.5 py-1 text-xs font-semibold capitalize ${locked ? 'bg-slate-100 text-slate-600' : 'bg-emerald-50 text-emerald-800'}`}>
                                {selectedAcademicYear.status}
                            </span>
                        )}
                    </div>
                    <p className="mt-1 text-sm text-slate-500">{locked ? 'This year is locked: fees and payments are read-only.' : 'Billing and collection for the selected year.'}</p>
                </div>
                <YearSelect years={academicYears} value={yearId} onChange={(value) => router.get('/fees', { academic_year_id: value }, { preserveScroll: true })} />
            </section>

            <section aria-label="Fee summary" className="mb-8 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                {[
                    ['Billed', summary.billed, ReceiptText, 'text-slate-700', 'bg-slate-100'],
                    ['Collected', summary.paid, CircleDollarSign, 'text-emerald-800', 'bg-emerald-50'],
                    ['Outstanding', summary.outstanding, TrendingUp, 'text-amber-800', 'bg-amber-50'],
                ].map(([label, value, Icon, tone, iconTone]) => (
                    <article key={label} className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div className="flex items-start justify-between gap-3">
                            <div>
                                <p className="text-sm font-medium text-slate-500">{label}</p>
                                <p className={`mt-3 text-2xl font-bold tracking-tight ${tone}`}>{money(value)}</p>
                            </div>
                            <span className={`flex size-10 items-center justify-center rounded-xl ${iconTone} ${tone}`}>
                                <Icon size={19} aria-hidden="true" />
                            </span>
                        </div>
                    </article>
                ))}
                <Link href={`/invoices?status=overdue${yearId ? `&academic_year_id=${yearId}` : ''}`} className="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-rose-200 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-600 focus-visible:ring-offset-2">
                    <div className="flex items-start justify-between gap-3">
                        <div>
                            <p className="text-sm font-medium text-slate-500">Overdue invoices</p>
                            <p className={`mt-3 text-2xl font-bold tracking-tight ${overdueCount > 0 ? 'text-rose-700' : 'text-slate-900'}`}>{overdueCount.toLocaleString()}</p>
                        </div>
                        <span className={`flex size-10 items-center justify-center rounded-xl ${overdueCount > 0 ? 'bg-rose-50 text-rose-700' : 'bg-slate-100 text-slate-500'}`}>
                            <AlertTriangle size={19} aria-hidden="true" />
                        </span>
                    </div>
                    <span className="mt-2 inline-block text-xs font-semibold text-rose-700 group-hover:text-rose-900">Review invoices <span aria-hidden="true">→</span></span>
                </Link>
            </section>

            <section>
                <div className="mb-4">
                    <h2 className="font-semibold text-slate-900">Fee workflow</h2>
                    <p className="mt-1 text-sm text-slate-500">Follow each step from setup through payment collection.</p>
                </div>
                <ol className="grid gap-4 lg:grid-cols-2">
                    {steps.map((step, index) => (
                        <li key={step.key} className={`rounded-2xl border bg-white p-5 shadow-sm transition-shadow hover:shadow-md sm:p-6 ${step.current ? 'border-teal-300 ring-2 ring-teal-100' : 'border-slate-200'}`}>
                            <div className="flex items-start justify-between gap-3">
                                <div className="flex items-center gap-3">
                                    <span className={`flex size-9 items-center justify-center rounded-xl text-sm font-bold ${step.done ? 'bg-emerald-100 text-emerald-800' : step.current ? 'bg-teal-800 text-white shadow-sm' : 'bg-slate-100 text-slate-600'}`} aria-label={step.done ? 'Completed' : `Step ${index + 1}`}>{step.done ? '✓' : index + 1}</span>
                                    <h3 className="font-semibold text-slate-900">{step.title}</h3>
                                </div>
                                <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${step.done ? 'bg-emerald-50 text-emerald-800' : step.current ? 'bg-teal-50 text-teal-800' : 'bg-slate-100 text-slate-600'}`}>{step.done ? 'Done' : step.current ? 'Next step' : 'To do'}</span>
                            </div>
                            <p className="mt-3 text-sm leading-6 text-slate-600">{step.description}</p>
                            <p className="mt-2 text-sm font-medium text-slate-800">{step.metric}</p>
                            <Link href={step.href} className="mt-4 inline-flex items-center gap-1.5 rounded-lg text-sm font-semibold text-teal-800 hover:text-teal-950 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-700 focus-visible:ring-offset-2">{step.action}<span aria-hidden="true">→</span></Link>
                        </li>
                    ))}
                </ol>
            </section>
        </AppLayout>
    );
}