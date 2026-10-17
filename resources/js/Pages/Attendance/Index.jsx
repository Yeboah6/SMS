import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import PageAlerts from '../../Components/Exams/PageAlerts';
import { Field, inputClass } from '../Auth/Login';
import { dateLabel, shiftDate } from '../../lib/attendance';

export default function AttendanceIndex({ academicYears, selectedAcademicYearId, yearStart, yearEnd, attendanceDate, registers, summary, yearLocked, can }) {
    function visit(params) {
        router.get('/attendance', { academic_year_id: selectedAcademicYearId, date: attendanceDate, ...params }, { preserveScroll: true });
    }

    const previous = yearStart && shiftDate(attendanceDate, -1) >= yearStart ? shiftDate(attendanceDate, -1) : null;
    const next = yearEnd && shiftDate(attendanceDate, 1) <= yearEnd ? shiftDate(attendanceDate, 1) : null;

    return (
        <AppLayout title="Attendance" description="See which class registers have been taken, and take or review them.">
            <Head title="Attendance" />
            <PageAlerts />

            <section className="mb-6 rounded-2xl border b   order-slate-200 bg-white p-5 sm:p-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h2 className="font-semibold text-slate-900">{attendanceDate ? dateLabel(attendanceDate) : 'No academic year'}</h2>
                        <p className="mt-1 text-sm text-slate-500">{yearLocked ? 'This year is locked: registers are read-only.' : 'Registers are stored per section and date.'}</p>
                    </div>
                    <div className="flex flex-wrap items-end gap-3">
                        <Field label="Academic year">
                            <select value={selectedAcademicYearId || ''} onChange={(event) => router.get('/attendance', { academic_year_id: event.target.value })} className={inputClass(false)}>
                                {academicYears.map((year) => <option key={year.id} value={year.id}>{year.name} · {year.status}</option>)}
                            </select>
                        </Field>
                        <div className="flex items-end gap-2">
                            <button type="button" disabled={!previous} onClick={() => visit({ date: previous })} aria-label="Previous day" className="rounded-xl border border-slate-200 px-3 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-40">‹</button>
                            <Field label="Date">
                                <input type="date" min={yearStart} max={yearEnd} value={attendanceDate} onChange={(event) => event.target.value && visit({ date: event.target.value })} className={inputClass(false)} />
                            </Field>
                            <button type="button" disabled={!next} onClick={() => visit({ date: next })} aria-label="Next day" className="rounded-xl border border-slate-200 px-3 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-40">›</button>
                        </div>
                    </div>
                </div>
                <dl className="mt-5 grid gap-3 text-sm sm:grid-cols-3">
                    <div className="rounded-xl bg-slate-50 px-4 py-3"><dt className="text-xs text-slate-500">Sections</dt><dd className="mt-1 text-lg font-semibold text-slate-900">{summary.sections}</dd></div>
                    <div className="rounded-xl bg-emerald-50 px-4 py-3"><dt className="text-xs text-emerald-800">Registers taken</dt><dd className="mt-1 text-lg font-semibold text-emerald-900">{summary.taken}</dd></div>
                    <div className={`rounded-xl px-4 py-3 ${summary.missing > 0 ? 'bg-amber-50' : 'bg-slate-50'}`}><dt className={`text-xs ${summary.missing > 0 ? 'text-amber-800' : 'text-slate-500'}`}>Not taken yet</dt><dd className={`mt-1 text-lg font-semibold ${summary.missing > 0 ? 'text-amber-900' : 'text-slate-900'}`}>{summary.missing}</dd></div>
                </dl>
            </section>

            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                {registers.length === 0 ? (
                    <p className="px-6 py-14 text-center text-sm text-slate-500">No class sections are available for this year{summary.sections === 0 ? ' (or none are assigned to you)' : ''}.</p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[760px] text-left text-sm">
                            <thead className="bg-slate-50 text-xs text-slate-500">
                                <tr><th className="px-5 py-3">Class section</th><th className="px-5 py-3">Register</th><th className="px-5 py-3">Present</th><th className="px-5 py-3">Absent</th><th className="px-5 py-3">Late</th><th className="px-5 py-3">Excused</th><th className="px-5 py-3"><span className="sr-only">Actions</span></th></tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {registers.map((register) => (
                                    <tr key={register.sectionId}>
                                        <td className="px-5 py-3.5 font-medium text-slate-900">{register.className} · {register.sectionName}</td>
                                        <td className="px-5 py-3.5"><span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${register.taken ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-800'}`}>{register.taken ? 'Taken' : 'Not taken'}</span></td>
                                        <td className="px-5 py-3.5 text-slate-700">{register.taken ? register.present : '—'}</td>
                                        <td className="px-5 py-3.5 text-slate-700">{register.taken ? register.absent : '—'}</td>
                                        <td className="px-5 py-3.5 text-slate-700">{register.taken ? register.late : '—'}</td>
                                        <td className="px-5 py-3.5 text-slate-700">{register.taken ? register.excused : '—'}</td>
                                        <td className="px-5 py-3.5">
                                            <div className="flex items-center justify-end gap-3 text-xs font-semibold">
                                                {register.taken ? (
                                                    <>
                                                        <Link href={`/attendance/${register.attendanceId}`} className="text-teal-800 hover:text-teal-950">View</Link>
                                                        {can.update && <Link href={`/attendance/${register.attendanceId}/edit`} className="text-teal-800 hover:text-teal-950">Edit</Link>}
                                                    </>
                                                ) : can.mark ? (
                                                    <Link href={`/attendance/create?academic_year_id=${selectedAcademicYearId}&section_id=${register.sectionId}&date=${attendanceDate}`} className="rounded-lg bg-teal-800 px-3 py-1.5 text-white hover:bg-teal-900">Take attendance</Link>
                                                ) : <span className="text-slate-400">—</span>}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>
        </AppLayout>
    );
}