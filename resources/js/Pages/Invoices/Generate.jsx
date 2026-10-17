import { Head, Link, router, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import PageAlerts from '../../Components/Exams/PageAlerts';
import FeesNav from '../../Components/Fees/FeesNav';
import YearSelect from '../../Components/Fees/YearSelect';
import { Field, inputClass } from '../Auth/Login';
import { dateLabel, useMoney } from '../../lib/fees';

export default function InvoicesGenerate({ academicYears, selectedAcademicYearId, classes, selectedClassId, preview }) {
    const money = useMoney();
    const form = useForm({ academic_year_id: String(selectedAcademicYearId), class_id: selectedClassId ? String(selectedClassId) : '' });

    function load(params) {
        router.get('/invoices/generate', params, { preserveScroll: true });
    }

    function submit(event) {
        event.preventDefault();
        form.post('/invoices/generate');
    }

    const noFees = preview && preview.fees.length === 0;
    const noStudents = preview && preview.enrolledStudents === 0;
    const blocked = !preview || noFees || noStudents;

    return (
        <AppLayout title="Generate invoices" description="Bill a whole class from its fee structure. Safe to repeat: nothing is duplicated.">
            <Head title="Generate invoices" />
            <PageAlerts />
            <FeesNav current="invoices" yearId={selectedAcademicYearId} />

            <div className="mb-6"><Link href={`/invoices?academic_year_id=${selectedAcademicYearId}`} className="text-sm font-semibold text-teal-800 hover:text-teal-950">Back to invoices</Link></div>

            <section className="mb-6 grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 sm:grid-cols-2 sm:p-6">
                <YearSelect years={academicYears} value={selectedAcademicYearId} onChange={(value) => load({ academic_year_id: value })} />
                <Field label="Class">
                    <select value={selectedClassId || ''} onChange={(event) => load({ academic_year_id: selectedAcademicYearId, ...(event.target.value ? { class_id: event.target.value } : {}) })} className={inputClass(false)}>
                        <option value="">Select class</option>
                        {classes.map((schoolClass) => <option key={schoolClass.id} value={schoolClass.id}>{schoolClass.name}</option>)}
                    </select>
                </Field>
            </section>

            {!preview ? (
                <p className="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center text-sm text-slate-500">Choose a class to see what will be billed.</p>
            ) : (
                <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <h2 className="font-semibold text-slate-900">What will happen</h2>

                    {noFees && <p className="mt-4 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900">This class has no fees for the year. <Link href={`/fee-structures/create?academic_year_id=${selectedAcademicYearId}&class_id=${selectedClassId}`} className="font-semibold underline">Add fees first</Link>.</p>}
                    {noStudents && <p className="mt-4 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900">No students are actively enrolled in this class for the year.</p>}

                    {!noFees && (
                        <div className="mt-4">
                            <p className="text-sm font-medium text-slate-800">Each student is billed {money(preview.perStudentTotal)}:</p>
                            <ul className="mt-2 divide-y divide-slate-100 rounded-xl border border-slate-100 text-sm">
                                {preview.fees.map((fee) => (
                                    <li key={fee.name} className="flex justify-between gap-3 px-3 py-2"><span className="text-slate-700">{fee.name} · due {dateLabel(fee.dueDate)}</span><span className="font-medium text-slate-900">{money(fee.amount)}</span></li>
                                ))}
                            </ul>
                        </div>
                    )}

                    {!noStudents && (
                        <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-3">
                            <div className="rounded-xl bg-slate-50 px-4 py-3"><dt className="text-xs text-slate-500">Enrolled students</dt><dd className="mt-1 text-lg font-semibold text-slate-900">{preview.enrolledStudents}</dd></div>
                            <div className="rounded-xl bg-slate-50 px-4 py-3"><dt className="text-xs text-slate-500">Will get a new invoice</dt><dd className="mt-1 text-lg font-semibold text-slate-900">{preview.withoutInvoice}</dd></div>
                            <div className="rounded-xl bg-slate-50 px-4 py-3"><dt className="text-xs text-slate-500">Already invoiced</dt><dd className="mt-1 text-lg font-semibold text-slate-900">{preview.alreadyInvoiced}</dd></div>
                        </dl>
                    )}
                    <p className="mt-4 text-xs text-slate-500">Students who already have an invoice only receive fee items they are missing. Existing amounts and payments are never changed.</p>

                    <form onSubmit={submit} className="mt-5">
                        {form.errors.academic_year_id && <p role="alert" className="mb-3 text-sm text-rose-700">{form.errors.academic_year_id}</p>}
                        {form.errors.class_id && <p role="alert" className="mb-3 text-sm text-rose-700">{form.errors.class_id}</p>}
                        <button type="submit" disabled={form.processing || blocked} className="rounded-xl bg-teal-800 px-5 py-3 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-50">
                            {form.processing ? 'Generating…' : 'Generate invoices'}
                        </button>
                    </form>
                </section>
            )}
        </AppLayout>
    );
}