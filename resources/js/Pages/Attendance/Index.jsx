import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import PageAlerts from '../../Components/Exams/PageAlerts';
import Pagination from '../../Components/Pagination';
import { Field, inputClass } from '../Auth/Login';
import { dateLabel, shiftDate } from '../../lib/attendance';

export default function AttendanceIndex({ academicYears, selectedAcademicYearId, yearStart, yearEnd, attendanceDate, registers, summary, yearLocked, can }) {
    const registerItems = registers.data || [];

    function visit(params) {
        router.get('/attendance', { academic_year_id: selectedAcademicYearId, date: attendanceDate, ...params }, { preserveScroll: true });
    }

    const previous = yearStart && shiftDate(attendanceDate, -1) >= yearStart ? shiftDate(attendanceDate, -1) : null;
    const next = yearEnd && shiftDate(attendanceDate, 1) <= yearEnd ? shiftDate(attendanceDate, 1) : null;

    const takeUrl = (register) => `/attendance/create?academic_year_id=${selectedAcademicYearId}&section_id=${register.sectionId}&date=${attendanceDate}`;

    return (
        <AppLayout title="Attendance" description="See which class registers have been taken, and take or review them.">
            <Head title="Attendance" />
            <PageAlerts />

            <section className="mb-4 rounded-2xl border border-slate-200 bg-white p-4 sm:mb-6 sm:p-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h2 className="font-semibold text-slate-900">{attendanceDate ? dateLabel(attendanceDate) : 'No academic year'}</h2>
                        <p className="mt-1 text-sm text-slate-500">{yearLocked ? 'This year is locked: registers are read-only.' : 'Registers are stored per section and date.'}</p>
                    </div>
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                        <div className="w-full sm:w-auto">
                            <Field label="Academic year">
                                <select value={selectedAcademicYearId || ''} onChange={(event) => router.get('/attendance', { academic_year_id: event.target.value })} className={`${inputClass(false)} w-full`}>
                                    {academicYears.map((year) => <option key={year.id} value={year.id}>{year.name} · {year.status}</option>)}
                                </select>
                            </Field>
                        </div>
                        <div className="flex w-full items-end gap-2 sm:w-auto">
                            <button type="button" disabled={!previous} onClick={() => visit({ date: previous })} aria-label="Previous day" className="shrink-0 rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-40">‹</button>
                            <div className="min-w-0 flex-1 sm:flex-none">
                                <Field label="Date">
                                    <input type="date" min={yearStart} max={yearEnd} value={attendanceDate} onChange={(event) => event.target.value && visit({ date: event.target.value })} className={`${inputClass(false)} w-full`} />
                                </Field>
                            </div>
                            <button type="button" disabled={!next} onClick={() => visit({ date: next })} aria-label="Next day" className="shrink-0 rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-40">›</button>
                        </div>
                    </div>
                </div>

                <dl className="mt-4 grid grid-cols-3 gap-2 text-sm sm:mt-5 sm:gap-3">
                    <div className="rounded-xl bg-slate-50 px-3 py-2.5 sm:px-4 sm:py-3"><dt className="text-xs text-slate-500">Sections</dt><dd className="mt-1 text-lg font-semibold text-slate-900">{summary.sections}</dd></div>
                    <div className="rounded-xl bg-emerald-50 px-3 py-2.5 sm:px-4 sm:py-3"><dt className="text-xs text-emerald-800">Taken</dt><dd className="mt-1 text-lg font-semibold text-emerald-900">{summary.taken}</dd></div>
                    <div className={`rounded-xl px-3 py-2.5 sm:px-4 sm:py-3 ${summary.missing > 0 ? 'bg-amber-50' : 'bg-slate-50'}`}><dt className={`text-xs ${summary.missing > 0 ? 'text-amber-800' : 'text-slate-500'}`}>Not taken</dt><dd className={`mt-1 text-lg font-semibold ${summary.missing > 0 ? 'text-amber-900' : 'text-slate-900'}`}>{summary.missing}</dd></div>
                </dl>
            </section>

            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                {registerItems.length === 0 ? (
                    <p className="px-6 py-14 text-center text-sm text-slate-500">No class sections are available for this year{summary.sections === 0 ? ' (or none are assigned to you)' : ''}.</p>
                ) : (
                    <>
                        {/* Mobile: one card per register */}
                        <ul className="divide-y divide-slate-100 md:hidden">
                            {registerItems.map((register) => (
                                <li key={register.sectionId} className="p-4">
                                    <div className="flex items-start justify-between gap-3">
                                        <p className="min-w-0 font-medium text-slate-900">{register.className} · {register.sectionName}</p>
                                        <StatusBadge taken={register.taken} />
                                    </div>

                                    {register.taken && (
                                        <dl className="mt-3 grid grid-cols-4 gap-2 text-center">
                                            <Count label="Present" value={register.present} />
                                            <Count label="Absent" value={register.absent} />
                                            <Count label="Late" value={register.late} />
                                            <Count label="Excused" value={register.excused} />
                                        </dl>
                                    )}

                                    <div className="mt-3 flex gap-2 text-sm font-semibold">
                                        {register.taken ? (
                                            <>
                                                <Link href={`/attendance/${register.attendanceId}`} className="flex-1 rounded-lg border border-slate-200 px-3 py-2.5 text-center text-teal-800 hover:bg-slate-50">View</Link>
                                                {can.update && <Link href={`/attendance/${register.attendanceId}/edit`} className="flex-1 rounded-lg border border-slate-200 px-3 py-2.5 text-center text-teal-800 hover:bg-slate-50">Edit</Link>}
                                            </>
                                        ) : can.mark ? (
                                            <Link href={takeUrl(register)} className="flex-1 rounded-lg bg-teal-800 px-3 py-2.5 text-center text-white hover:bg-teal-900">Take attendance</Link>
                                        ) : null}
                                    </div>
                                </li>
                            ))}
                        </ul>

                        {/* Tablet and up: table */}
                        <div className="hidden overflow-x-auto md:block">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-slate-50 text-xs text-slate-500">
                                    <tr><th className="px-5 py-3">Class section</th><th className="px-5 py-3">Register</th><th className="px-5 py-3">Present</th><th className="px-5 py-3">Absent</th><th className="px-5 py-3">Late</th><th className="px-5 py-3">Excused</th><th className="px-5 py-3"><span className="sr-only">Actions</span></th></tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {registerItems.map((register) => (
                                        <tr key={register.sectionId}>
                                            <td className="px-5 py-3.5 font-medium text-slate-900">{register.className} · {register.sectionName}</td>
                                            <td className="px-5 py-3.5"><StatusBadge taken={register.taken} /></td>
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
                                                        <Link href={takeUrl(register)} className="rounded-lg bg-teal-800 px-3 py-1.5 text-white hover:bg-teal-900">Take attendance</Link>
                                                    ) : <span className="text-slate-400">—</span>}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </>
                )}
                {registerItems.length > 0 && <Pagination links={registers.links} from={registers.from} to={registers.to} total={registers.total} />}
            </section>
        </AppLayout>
    );
}

function StatusBadge({ taken }) {
    return (
        <span className={`shrink-0 whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold ${taken ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-800'}`}>
            {taken ? 'Taken' : 'Not taken'}
        </span>
    );
}

function Count({ label, value }) {
    return (
        <div className="rounded-lg bg-slate-50 px-1 py-2">
            <dt className="text-[11px] text-slate-500">{label}</dt>
            <dd className="mt-0.5 text-base font-semibold text-slate-900">{value}</dd>
        </div>
    );
}