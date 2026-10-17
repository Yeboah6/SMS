import { Head, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { inputClass } from '../Auth/Login';

const percentageFormat = new Intl.NumberFormat(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

function formatPercentage(value) {
    return value === null ? '—' : `${percentageFormat.format(value)}%`;
}

export default function AcademicResultsReport({
    academicYears,
    selectedAcademicYearId,
    selectedClassId,
    selectedTermId,
    selectedExamId,
    classes,
    terms,
    exams,
    selectedExam,
    summary,
    students,
    canExport,
}) {
    const exportParams = new URLSearchParams();
    if (selectedAcademicYearId) {
        exportParams.set('academic_year_id', selectedAcademicYearId);
    }
    if (selectedClassId) {
        exportParams.set('class_id', selectedClassId);
    }
    if (selectedTermId) {
        exportParams.set('term_id', selectedTermId);
    }
    if (selectedExamId) {
        exportParams.set('exam_id', selectedExamId);
    }

    return (
        <AppLayout
            title="Academic results report"
            description="Review published examination performance by academic year, class, and term."
        >
            <Head title="Academic results report" />

            <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <form method="GET" action="/reports/academic" className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <label className="grid gap-1.5 text-sm font-medium text-slate-700">
                        Academic year
                        <select name="academic_year_id" defaultValue={selectedAcademicYearId || ''} className={inputClass(false)}>
                            <option value="">Active academic year</option>
                            {academicYears.map((year) => (
                                <option key={year.id} value={year.id}>{year.name}</option>
                            ))}
                        </select>
                    </label>
                    <label className="grid gap-1.5 text-sm font-medium text-slate-700">
                        Class
                        <select name="class_id" defaultValue={selectedClassId || ''} className={inputClass(false)}>
                            <option value="">All classes</option>
                            {classes.map((schoolClass) => (
                                <option key={schoolClass.id} value={schoolClass.id}>{schoolClass.name}</option>
                            ))}
                        </select>
                    </label>
                    <label className="grid gap-1.5 text-sm font-medium text-slate-700">
                        Term
                        <select name="term_id" defaultValue={selectedTermId || ''} className={inputClass(false)}>
                            <option value="">All terms</option>
                            {terms.map((term) => (
                                <option key={term.id} value={term.id}>{term.name}</option>
                            ))}
                        </select>
                    </label>
                    <label className="grid gap-1.5 text-sm font-medium text-slate-700">
                        Published examination
                        <select name="exam_id" defaultValue={selectedExamId || ''} className={inputClass(false)}>
                            <option value="">Latest matching exam</option>
                            {exams.map((exam) => (
                                <option key={exam.id} value={exam.id}>
                                    {exam.name} · {exam.className}{exam.termName ? ` · ${exam.termName}` : ''}
                                </option>
                            ))}
                        </select>
                    </label>
                    <div className="flex items-center gap-2 sm:col-span-2 xl:col-span-4">
                        <button type="submit" className="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-800">
                            Apply filters
                        </button>
                        {canExport && selectedExamId && (
                            <a href={`/reports/academic/export?${exportParams.toString()}`} className="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                Download CSV
                            </a>
                        )}
                    </div>
                </form>
            </section>

            {selectedExam ? (
                <>
                    <section aria-label="Examination performance totals" className="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <article className="rounded-2xl border border-slate-200 bg-white p-4">
                            <p className="text-sm font-medium text-slate-500">Candidates</p>
                            <p className="mt-2 text-2xl font-bold tracking-tight text-slate-950">{summary.candidateCount.toLocaleString()}</p>
                        </article>
                        <article className="rounded-2xl border border-slate-200 bg-white p-4">
                            <p className="text-sm font-medium text-slate-500">Average percentage</p>
                            <p className="mt-2 text-2xl font-bold tracking-tight text-teal-800">{formatPercentage(summary.averagePercentage)}</p>
                        </article>
                        <article className="rounded-2xl border border-slate-200 bg-white p-4">
                            <p className="text-sm font-medium text-slate-500">Highest percentage</p>
                            <p className="mt-2 text-2xl font-bold tracking-tight text-emerald-700">{formatPercentage(summary.highestPercentage)}</p>
                        </article>
                        <article className="rounded-2xl border border-slate-200 bg-white p-4">
                            <p className="text-sm font-medium text-slate-500">Passed all assessed subjects</p>
                            <p className="mt-2 text-2xl font-bold tracking-tight text-slate-950">{summary.passedAllSubjects.toLocaleString()}</p>
                        </article>
                    </section>

                    <section className="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white">
                        <div className="flex flex-col gap-1 border-b border-slate-100 px-5 py-4 sm:px-6">
                            <h2 className="font-semibold text-slate-900">{selectedExam.name} · {selectedExam.className}</h2>
                            <p className="text-sm text-slate-500">
                                {selectedExam.termName ? `${selectedExam.termName} · ` : ''}{students.total.toLocaleString()} candidates
                            </p>
                            <p className="text-xs text-slate-500">Exempt subjects are excluded from the maximum marks; absent marks count as zero.</p>
                        </div>
                        {students.data.length === 0 ? (
                            <div className="px-6 py-12 text-center">
                                <p className="font-medium text-slate-800">No published marks found</p>
                                <p className="mt-1 text-sm text-slate-500">This examination has no result rows to report.</p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-slate-100 text-left text-sm">
                                    <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                        <tr>
                                            <th className="px-5 py-3 font-semibold">Student</th>
                                            <th className="px-5 py-3 text-right font-semibold">Total marks</th>
                                            <th className="px-5 py-3 text-right font-semibold">Percentage</th>
                                            <th className="px-5 py-3 text-right font-semibold">Subjects passed</th>
                                            <th className="px-5 py-3 text-right font-semibold">Subjects assessed</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {students.data.map((student) => (
                                            <tr key={student.id} className="hover:bg-slate-50/70">
                                                <td className="whitespace-nowrap px-5 py-3.5">
                                                    <p className="font-medium text-slate-900">{student.name}</p>
                                                    <p className="mt-0.5 text-xs text-slate-500">{student.admissionNumber}</p>
                                                </td>
                                                <td className="whitespace-nowrap px-5 py-3.5 text-right font-medium text-slate-700">
                                                    {percentageFormat.format(student.marksObtained)} / {percentageFormat.format(student.maximumMarks)}
                                                </td>
                                                <td className="whitespace-nowrap px-5 py-3.5 text-right font-semibold text-slate-900">{formatPercentage(student.percentage)}</td>
                                                <td className="whitespace-nowrap px-5 py-3.5 text-right text-emerald-700">{student.passedSubjects}</td>
                                                <td className="whitespace-nowrap px-5 py-3.5 text-right text-slate-600">{student.assessedSubjects}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                        {students.links.length > 3 && (
                            <nav aria-label="Academic results report pages" className="flex flex-wrap justify-between gap-2 border-t border-slate-100 px-5 py-4">
                                {students.links.map((link) => (
                                    <button
                                        key={link.label}
                                        type="button"
                                        disabled={!link.url || link.active}
                                        onClick={() => link.url && router.get(link.url, {}, { preserveScroll: true })}
                                        aria-current={link.active ? 'page' : undefined}
                                        className={`rounded-lg px-3 py-2 text-sm ${link.active ? 'bg-teal-50 font-semibold text-teal-800' : 'text-slate-600'} disabled:opacity-40`}
                                    >
                                        {link.label.replace('&laquo;', '‹').replace('&raquo;', '›')}
                                    </button>
                                ))}
                            </nav>
                        )}
                    </section>
                </>
            ) : (
                <section className="mt-6 rounded-2xl border border-slate-200 bg-white px-6 py-12 text-center">
                    <p className="font-medium text-slate-800">No published examinations found</p>
                    <p className="mt-1 text-sm text-slate-500">Choose another academic year or publish an examination first.</p>
                </section>
            )}
        </AppLayout>
    );
}
