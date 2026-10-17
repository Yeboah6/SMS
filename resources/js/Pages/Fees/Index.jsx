import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import PageAlerts from '../../Components/Exams/PageAlerts';
import FeesNav from '../../Components/Fees/FeesNav';
import YearSelect from '../../Components/Fees/YearSelect';
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

            <section className="mb-6 flex flex-wrap items-end justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-5">
                <div>
                    <h2 className="font-semibold text-slate-900">{selectedAcademicYear ? selectedAcademicYear.name : 'No academic year'}</h2>
                    <p className="mt-1 text-sm text-slate-500">{locked ? 'This year is locked: fees and payments are read-only.' : 'Billing and collection for the selected year.'}</p>
                </div>
                <YearSelect years={academicYears} value={yearId} onChange={(value) => router.get('/fees', { academic_year_id: value }, { preserveScroll: true })} />
            </section>

            <section className="mb-6 grid gap-3 sm:grid-cols-4">
                {[['Billed', summary.billed], ['Collected', summary.paid], ['Outstanding', summary.outstanding]].map(([label, value]) => (
                    <article key={label} className="rounded-2xl border border-slate-200 bg-white p-5">
                        <p className="text-sm text-slate-500">{label}</p>
                        <p className="mt-2 text-2xl font-semibold tracking-tight text-slate-900">{money(value)}</p>
                    </article>
                ))}
                <Link href={`/invoices?status=overdue${yearId ? `&academic_year_id=${yearId}` : ''}`} className="rounded-2xl border border-slate-200 bg-white p-5 hover:border-rose-200">
                    <p className="text-sm text-slate-500">Overdue invoices</p>
                    <p className={`mt-2 text-2xl font-semibold tracking-tight ${overdueCount > 0 ? 'text-rose-700' : 'text-slate-900'}`}>{overdueCount}</p>
                </Link>
            </section>

            <section>
                <h2 className="mb-3 font-semibold text-slate-900">How fees work</h2>
                <ol className="grid gap-3 lg:grid-cols-2">
                    {steps.map((step, index) => (
                        <li key={step.key} className={`rounded-2xl border bg-white p-5 ${step.current ? 'border-teal-400 ring-2 ring-teal-100' : 'border-slate-200'}`}>
                            <div className="flex items-start justify-between gap-3">
                                <div className="flex items-center gap-3">
                                    <span className={`flex size-8 items-center justify-center rounded-full text-sm font-bold ${step.done ? 'bg-emerald-100 text-emerald-800' : step.current ? 'bg-teal-800 text-white' : 'bg-slate-100 text-slate-600'}`}>{step.done ? '✓' : index + 1}</span>
                                    <h3 className="font-semibold text-slate-900">{step.title}</h3>
                                </div>
                                <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${step.done ? 'bg-emerald-50 text-emerald-800' : step.current ? 'bg-teal-50 text-teal-800' : 'bg-slate-100 text-slate-600'}`}>{step.done ? 'Done' : step.current ? 'Next step' : 'To do'}</span>
                            </div>
                            <p className="mt-3 text-sm leading-6 text-slate-600">{step.description}</p>
                            <p className="mt-2 text-sm font-medium text-slate-800">{step.metric}</p>
                            <Link href={step.href} className="mt-4 inline-block text-sm font-semibold text-teal-800 hover:text-teal-950">{step.action} →</Link>
                        </li>
                    ))}
                </ol>
            </section>
        </AppLayout>
    );
}