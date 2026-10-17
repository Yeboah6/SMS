import { Link } from '@inertiajs/react';
import { dateLabel, humanize, statusTone } from '../../lib/exams';

export default function ResultCard({ result, detailHref = null }) {
    return (
        <article className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 className="text-lg font-semibold text-slate-900">
                        {detailHref ? <Link href={detailHref} className="hover:text-teal-800">{result.name}</Link> : result.name}
                    </h2>
                    <p className="mt-1 text-sm text-slate-600">{result.academicYear} · {result.className} · Published {dateLabel(result.publishedAt)}</p>
                </div>
                <div className="flex items-center gap-2">
                    <span className="rounded-full bg-teal-50 px-3 py-1 text-xs font-semibold capitalize text-teal-800">{humanize(result.examType)}</span>
                    <span className={`rounded-full px-3 py-1 text-xs font-semibold capitalize ${statusTone(result.result)}`}>{result.result}</span>
                </div>
            </div>
            <div className="mt-5 overflow-x-auto">
                <table className="w-full min-w-[580px] text-left text-sm">
                    <thead className="border-b border-slate-100 text-xs text-slate-500">
                        <tr>
                            <th className="py-3 pr-4">Subject</th>
                            <th className="py-3 pr-4">Exam date</th>
                            <th className="py-3 pr-4">Score</th>
                            <th className="py-3 pr-4">Pass mark</th>
                            <th className="py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {result.subjects.map((subject) => (
                            <tr key={subject.name}>
                                <td className="py-3 pr-4 font-medium text-slate-800">{subject.name}</td>
                                <td className="py-3 pr-4 text-slate-600">{dateLabel(subject.examDate)}</td>
                                <td className="py-3 pr-4 text-slate-700">{subject.status === 'exempt' ? 'Exempt' : subject.status === 'absent' ? `Absent / ${subject.maxMarks}` : `${subject.marksObtained} / ${subject.maxMarks}`}</td>
                                <td className="py-3 pr-4 text-slate-600">{subject.passMarks}</td>
                                <td className="py-3"><span className={`rounded-full px-2.5 py-1 text-xs font-semibold capitalize ${statusTone(subject.status)}`}>{humanize(subject.status)}</span></td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <div className="mt-4 flex flex-wrap gap-x-6 gap-y-2 border-t border-slate-100 pt-4 text-sm">
                <p className="text-slate-600">Total: <span className="font-semibold text-slate-900">{result.totalMarks} / {result.maximumMarks}</span></p>
                <p className="text-slate-600">Percentage: <span className="font-semibold text-slate-900">{result.percentage === null ? '—' : `${result.percentage}%`}</span></p>
                <p className="text-slate-600">Grade: <span className="font-semibold text-slate-900">{result.grade ?? '—'}</span></p>
            </div>
        </article>
    );
}