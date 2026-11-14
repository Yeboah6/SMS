import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

const dateLabel = (v) => (v ? new Date(`${v}T00:00:00`).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '');
const money = (v) => (v === null || v === undefined || v === '' ? '' : Number(v).toFixed(2));

export default function ResultsReportCard({ mode, studentId, exam, card }) {
    const query = studentId ? `?student_id=${studentId}` : '';
    const d = card.details;
    const hasFees = d.arrears != null || d.nextFee != null;
    const due = (Number(d.arrears) || 0) + (Number(d.nextFee) || 0);

    return (
        <AppLayout title="Report card" description={mode === 'parent' ? `Report card for ${card.student.name}` : 'Your report card'}>
            <Head title={`Report card – ${exam.name}`} />
            <style>{`
                @media print {
                    @page { size: A4; margin: 8mm; }
                    body * { visibility: hidden; }
                    .rc-print, .rc-print * { visibility: visible; }
                    .rc-print { position: absolute; left: 0; top: 0; width: 100%; }
                    .rc-sheet { box-shadow: none !important; max-width: none !important; margin: 0 !important; overflow: visible !important; }
                    .rc-table-wrap { overflow: visible !important; }
                }
            `}</style>

            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <Link href={`/results/${exam.id}${query}`} className="text-sm font-semibold text-teal-800 hover:text-teal-950">Back to result</Link>
                <div className="flex items-center gap-3">
                    <button type="button" onClick={() => window.print()} className="rounded-lg border border-teal-700 px-3 py-2 text-sm font-semibold text-teal-800 hover:bg-teal-50">Print</button>
                    <a href={`/results/${exam.id}/report-card/download${query}`} className="rounded-lg bg-teal-700 px-3 py-2 text-sm font-semibold text-white hover:bg-teal-800">Download PDF</a>
                </div>
            </div>

            <section className="rc-print">
                <div className="rc-sheet mx-auto max-w-3xl border-2 border-slate-800 bg-white p-4 text-sm text-slate-900 shadow-sm">
                    <div className="mb-3 flex items-center justify-center gap-3 text-center">
                        {exam.school?.logoUrl && <img src={exam.school.logoUrl} alt="" className="h-14 w-14 object-contain" />}
                        <div>
                            <div className="text-base font-bold uppercase tracking-wide">{exam.school?.name}</div>
                            {exam.school?.motto && <div className="text-xs italic text-slate-600">{exam.school.motto}</div>}
                    {exam.school?.contact && <div className="text-xs text-slate-600">{exam.school.contact}</div>}
                    <div className="text-xs text-slate-600">Terminal Report · {exam.name}</div>
                        </div>
                    </div>
                    <div className="mb-2 flex flex-wrap justify-between gap-2 border-b border-slate-300 pb-2 font-semibold">
                        <span>{card.student.name}</span>
                        <span className="font-normal text-slate-600">{card.student.admissionNumber}</span>
                    </div>

                    <div className="space-y-2 border-b-2 border-slate-800 pb-3">
                        <Line><Item label="Form" value={exam.className} /><Item label="Term" value={exam.termName} /><Item label="Year" value={exam.academicYear} /></Line>
                        <Line><Item label="Date of report" value={dateLabel(exam.reportDate)} /><Item label="Closing date" value={dateLabel(d.closing)} /></Line>
                        <Line><Item label="Next term begins" value={dateLabel(d.nextBegins)} /><Item label="Next term ends" value={dateLabel(d.nextEnds)} /></Line>
                        <Line><Item label="Attendance obtained" value={d.attendance} /><Item label="Out of" value={d.outOf} /></Line>
                    </div>

                    <div className="rc-table-wrap max-w-full overflow-x-auto">
                        <table className="w-full min-w-[42rem] border-collapse text-left">
                            <thead>
                                <tr className="border-b-2 border-slate-800 text-center text-xs font-bold uppercase">
                                    <th className="border-r-2 border-slate-800 p-2 text-left">Subject</th>
                                    <th className="border-r border-slate-800 p-2">Class score<br />30%</th>
                                    <th className="border-r border-slate-800 p-2">Exams score<br />70%</th>
                                    <th className="border-r border-slate-800 p-2">Total score<br />100%</th>
                                    <th className="border-r border-slate-800 p-2">Position</th>
                                    <th className="p-2">Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                {card.rows.map((r) => (
                                    <tr key={r.subjectId} className="border-b border-slate-800 text-center">
                                        <td className="border-r-2 border-slate-800 p-2 text-left">{r.subject}</td>
                                        <td className="border-r border-slate-800 p-2">{r.classScore ?? ''}</td>
                                        <td className="border-r border-slate-800 p-2">{r.examScore ?? ''}</td>
                                        <td className="border-r border-slate-800 p-2 font-semibold">{r.total ?? ''}</td>
                                        <td className="border-r border-slate-800 p-2">{r.position ?? ''}</td>
                                        <td className="p-2">{r.remark ?? ''}</td>
                                    </tr>
                                ))}
                                <tr className="border-y-2 border-slate-800 text-center font-bold">
                                    <td className="border-r-2 border-slate-800 p-2 text-left">TOTAL</td>
                                    <td className="border-r border-slate-800" />
                                    <td className="border-r border-slate-800" />
                                    <td className="border-r border-slate-800 p-2">{card.grandTotal}</td>
                                    <td className="border-r border-slate-800 p-2">{card.overallPosition ? `${card.overallPosition} of ${card.classSize}` : ''}</td>
                                    <td />
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div className="mt-3 space-y-2">
                        <Item label="Conduct" value={d.conduct} block />
                        <Item label="Attitude" value={d.attitude} block />
                        <Item label="Class Teacher’s Remarks" value={d.teacherRemarks} block />
                        <div className="space-y-2 pt-3 sm:w-2/3">
                            <Item label="School Fees Arrears GH₵" value={money(d.arrears)} block />
                            <Item label="Next Term’s Fee GH₵" value={money(d.nextFee)} block />
                            <Item label="Total Amount Due GH₵" value={hasFees ? due.toFixed(2) : ''} block />
                        </div>
                        <Item label="Head Teacher’s Remarks" value={d.headRemarks} block />
                    </div>
                </div>
            </section>
        </AppLayout>
    );
}

function Line({ children }) {
    return <div className="flex flex-wrap gap-x-6 gap-y-2">{children}</div>;
}

function Item({ label, value, block }) {
    return (
        <div className={`flex items-baseline gap-2 ${block ? 'w-full' : 'flex-1'}`}>
            <span className="whitespace-nowrap font-medium">{label}:</span>
            <span className="min-h-[1.25rem] min-w-0 flex-1 border-b border-dotted border-slate-500 px-1">{value ?? ''}</span>
        </div>
    );
}