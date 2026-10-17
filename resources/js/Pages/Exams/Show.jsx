import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import PageAlerts from '../../Components/Exams/PageAlerts';
import { dateLabel, humanize, statusTone } from '../../lib/exams';

export default function ExamsShow({ exam, subjects, can }) {
    function post(action) {
        router.post(`/exams/${exam.id}/${action}`, {}, { preserveScroll: true });
    }

    function deleteExam() {
        if (window.confirm(`Delete ${exam.name}? This cannot be undone.`)) {
            router.delete(`/exams/${exam.id}`);
        }
    }

    return (
        <AppLayout title={exam.name} description={`${exam.className} · ${exam.academicYear}`}>
            <Head title={exam.name} />
            <PageAlerts />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <Link href={`/exams?academic_year_id=${exam.academicYearId}`} className="text-sm font-semibold text-teal-800 hover:text-teal-950">Back to examinations</Link>
                <div className="flex flex-wrap items-center gap-3 text-sm font-semibold">
                    {can.update && <Link href={`/exams/${exam.id}/edit`} className="text-teal-800 hover:text-teal-950">Edit examination</Link>}
                    {can.delete && <button type="button" onClick={deleteExam} className="text-rose-700 hover:text-rose-900">Delete</button>}
                    {can.publish && <button type="button" onClick={() => post('publish')} className="rounded-lg bg-teal-700 px-3 py-2 text-white hover:bg-teal-800">Publish results</button>}
                    {can.reopen && <button type="button" onClick={() => post('reopen')} className="rounded-lg border border-amber-300 px-3 py-2 text-amber-800 hover:bg-amber-50">Reopen for correction</button>}
                </div>
            </div>

            <section className="mb-6 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h2 className="font-semibold text-slate-900">Details</h2>
                    <div className="flex items-center gap-2">
                        <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold capitalize text-slate-700">{humanize(exam.examType)}</span>
                        <span className={`rounded-full px-3 py-1 text-xs font-semibold capitalize ${statusTone(exam.status)}`}>{exam.status}</span>
                    </div>
                </div>
                <dl className="mt-4 grid gap-4 text-sm sm:grid-cols-2 xl:grid-cols-4">
                    <Detail label="Class" value={exam.className} />
                    <Detail label="Term" value={exam.termName || 'No term'} />
                    <Detail label="Dates" value={`${dateLabel(exam.startDate)} – ${dateLabel(exam.endDate)}`} />
                    <Detail label="Published" value={exam.publishedAt ? dateLabel(exam.publishedAt) : 'Not published'} />
                </dl>
                {exam.status === 'published' && !can.reopen && (
                    <p className="mt-4 text-sm text-slate-500">Results are published and read-only. An authorized user must reopen the examination before marks can be corrected.</p>
                )}
            </section>

            <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <h2 className="font-semibold text-slate-900">Subjects</h2>
                <div className="mt-4 overflow-x-auto">
                    <table className="w-full min-w-[640px] text-left text-sm">
                        <thead className="border-b border-slate-100 text-xs text-slate-500">
                            <tr>
                                <th className="py-3 pr-4">Subject</th>
                                <th className="py-3 pr-4">Exam date</th>
                                <th className="py-3 pr-4">Maximum</th>
                                <th className="py-3 pr-4">Pass mark</th>
                                <th className="py-3 pr-4">Marks entered</th>
                                <th className="py-3"><span className="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {subjects.map((subject) => (
                                <tr key={subject.id}>
                                    <td className="py-3 pr-4 font-medium text-slate-800">{subject.name}</td>
                                    <td className="py-3 pr-4 text-slate-600">{dateLabel(subject.examDate)}</td>
                                    <td className="py-3 pr-4 text-slate-600">{subject.maxMarks}</td>
                                    <td className="py-3 pr-4 text-slate-600">{subject.passMarks}</td>
                                    <td className="py-3 pr-4 text-slate-600">{subject.marksCount}</td>
                                    <td className="py-3 text-right">
                                        {can.viewMarks && <Link href={`/exams/${exam.id}/subjects/${subject.id}/marks`} className="text-xs font-semibold text-teal-800 hover:text-teal-950">{exam.status === 'draft' ? 'Enter marks' : 'View marks'}</Link>}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </section>
        </AppLayout>
    );
}

function Detail({ label, value }) {
    return (
        <div>
            <dt className="text-xs font-medium text-slate-500">{label}</dt>
            <dd className="mt-1 text-slate-900">{value}</dd>
        </div>
    );
}