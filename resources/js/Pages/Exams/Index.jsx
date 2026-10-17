import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import PageAlerts from '../../Components/Exams/PageAlerts';
import { Field, inputClass } from '../Auth/Login';
import { dateLabel, humanize, statusTone } from '../../lib/exams';

export default function ExamsIndex({ exams, academicYears, selectedAcademicYearId, canCreate }) {
    function selectYear(yearId) {
        router.get('/exams', yearId ? { academic_year_id: yearId } : {}, { preserveScroll: true });
    }

    return (
        <AppLayout title="Exams & results" description="Manage examinations, mark entry and publication.">
            <Head title="Exams & results" />
            <PageAlerts />

            <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h2 className="font-semibold text-slate-900">Examinations</h2>
                        <p className="mt-1 text-sm text-slate-500">{exams.length} examination{exams.length === 1 ? '' : 's'} in this academic year</p>
                    </div>
                    <div className="flex flex-wrap items-end gap-3">
                        <Field label="Academic year">
                            <select value={selectedAcademicYearId || ''} onChange={(event) => selectYear(event.target.value)} className={inputClass(false)}>
                                <option value="">Select academic year</option>
                                {academicYears.map((year) => <option key={year.id} value={year.id}>{year.name} · {year.status}</option>)}
                            </select>
                        </Field>
                        {canCreate && (
                            <Link href={`/exams/create?academic_year_id=${selectedAcademicYearId}`} className="rounded-xl bg-teal-800 px-4 py-3 text-sm font-semibold text-white hover:bg-teal-900">New examination</Link>
                        )}
                    </div>
                </div>

                {exams.length === 0 ? (
                    <p className="mt-5 rounded-xl bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">No examinations found for this academic year.</p>
                ) : (
                    <ul className="mt-5 grid gap-3">
                        {exams.map((exam) => (
                            <li key={exam.id}>
                                <Link href={`/exams/${exam.id}`} className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 p-4 transition hover:border-teal-200 hover:bg-slate-50">
                                    <span>
                                        <span className="block font-semibold text-slate-900">{exam.name}</span>
                                        <span className="mt-1 block text-sm text-slate-600">
                                            {exam.className}{exam.termName ? ` · ${exam.termName}` : ''} · {exam.subjectCount} subjects · {dateLabel(exam.startDate)} – {dateLabel(exam.endDate)}
                                        </span>
                                    </span>
                                    <span className="flex items-center gap-2">
                                        <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold capitalize text-slate-700">{humanize(exam.examType)}</span>
                                        <span className={`rounded-full px-3 py-1 text-xs font-semibold capitalize ${statusTone(exam.status)}`}>{exam.status}</span>
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </AppLayout>
    );
}