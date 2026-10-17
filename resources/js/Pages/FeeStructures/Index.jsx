import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import PageAlerts from '../../Components/Exams/PageAlerts';
import FeesNav from '../../Components/Fees/FeesNav';
import YearSelect from '../../Components/Fees/YearSelect';
import SpreadsheetImport from '../../Components/Fees/SpreadsheetImport';
import { dateLabel, useMoney } from '../../lib/fees';

export default function FeeStructuresIndex({ academicYears = [], selectedAcademicYear = null, classes = [], can = {} }) {
    const money = useMoney();
    const yearId = selectedAcademicYear?.id;
    const unpriced = classes.filter((schoolClass) => schoolClass.structures.length === 0).length;

    function deleteStructure(structure, className) {
        if (window.confirm(`Remove ${structure.feeHeadName} from ${className}? Existing invoices are not changed.`)) {
            router.delete(`/fee-structures/${structure.id}`, { preserveScroll: true });
        }
    }

    return (
        <AppLayout title="Fee structures" description="Step 2: what each class pays in the selected year.">
            <Head title="Fee structures" />
            <PageAlerts />
            <FeesNav current="structures" yearId={yearId} />

            <section className="mb-6 flex flex-wrap items-end justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-5">
                <div>
                    <h2 className="font-semibold text-slate-900">{selectedAcademicYear?.name || 'No academic year'}</h2>
                    <p className="mt-1 text-sm text-slate-500">
                        {selectedAcademicYear?.status === 'locked' ? 'This year is locked: structures are read-only.' : unpriced > 0 ? `${unpriced} of ${classes.length} classes have no fees yet. Invoices cannot be generated for them.` : 'Every class has fees set.'}
                    </p>
                </div>
                <div className="flex flex-wrap items-end gap-3">
                    {can.export && <a href={`/fee-structures/export?academic_year_id=${yearId}`} className="rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Export</a>}
                    {can.create && <Link href={`/fee-structures/create?academic_year_id=${yearId}`} className="rounded-xl bg-teal-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-900">Add fee</Link>}
                    <YearSelect years={academicYears} value={yearId} onChange={(value) => router.get('/fee-structures', { academic_year_id: value })} />
                </div>
            </section>

            {classes.length === 0 ? (
                <p className="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center text-sm text-slate-500">This year has no classes yet. Create classes first.</p>
            ) : (
                <div className="grid gap-4 xl:grid-cols-2">
                    {classes.map((schoolClass) => (
                        <article key={schoolClass.id} className="rounded-2xl border border-slate-200 bg-white p-5">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <h3 className="font-semibold text-slate-900">{schoolClass.name}</h3>
                                <span className="text-sm text-slate-500">Total per student <span className="font-semibold text-slate-900">{money(schoolClass.total)}</span></span>
                            </div>
                            {schoolClass.structures.length === 0 ? (
                                <p className="mt-3 rounded-xl bg-amber-50 px-3 py-3 text-sm text-amber-900">
                                    No fees set. {can.create && <Link href={`/fee-structures/create?academic_year_id=${yearId}&class_id=${schoolClass.id}`} className="font-semibold underline">Add the first fee</Link>}
                                </p>
                            ) : (
                                <ul className="mt-3 divide-y divide-slate-100 text-sm">
                                    {schoolClass.structures.map((structure) => (
                                        <li key={structure.id} className="flex flex-wrap items-center justify-between gap-2 py-2.5">
                                            <span className="text-slate-700">{structure.feeHeadName}<span className="block text-xs text-slate-500">Due {dateLabel(structure.dueDate)}</span></span>
                                            <span className="flex items-center gap-3">
                                                <span className="font-medium text-slate-900">{money(structure.amount)}</span>
                                                {can.update && <Link href={`/fee-structures/${structure.id}/edit`} className="text-xs font-semibold text-teal-800 hover:text-teal-950">Edit</Link>}
                                                {can.delete && <button type="button" onClick={() => deleteStructure(structure, schoolClass.name)} className="text-xs font-semibold text-rose-700 hover:text-rose-900">Remove</button>}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </article>
                    ))}
                </div>
            )}

            {can.import && (
                <section className="mt-6">
                    <SpreadsheetImport action="/fee-structures/import" label="Fee structure spreadsheet" description="Use a single-sheet CSV or XLSX file with existing academic-year, class, and fee-head names or codes. Locked years cannot be changed." />
                </section>
            )}
        </AppLayout>
    );
}