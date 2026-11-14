import { Link, useForm } from '@inertiajs/react';
import { STATUSES, statusTone } from '../../lib/attendance';

export default function AttendanceRegisterForm({ register, students, isEditing = false, cancelHref }) {
    const form = useForm({
        academic_year_id: register.academicYearId,
        section_id: register.sectionId,
        attendance_date: register.date,
        records: students.map((student) => ({ student_id: student.id, status: student.status, remarks: student.remarks })),
    });

    function setRecord(index, key, value) {
        form.setData('records', form.data.records.map((record, position) => (position === index ? { ...record, [key]: value } : record)));
    }

    function markAll(status) {
        form.setData('records', form.data.records.map((record) => ({ ...record, status })));
    }

    function save(event) {
        event.preventDefault();
        form.post('/attendance', { preserveScroll: true });
    }

    const counts = form.data.records.reduce((summary, record) => ({ ...summary, [record.status]: (summary[record.status] || 0) + 1 }), {});
    const error = form.errors.records || form.errors.academic_year_id || form.errors.section_id || form.errors.attendance_date;

    if (students.length === 0) {
        return (
            <div className="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center shadow-sm">
                <div className="mx-auto flex size-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-500">
                    <span className="text-xl font-semibold" aria-hidden="true">0</span>
                </div>
                <p className="mt-4 font-semibold text-slate-900">No students enrolled for this date</p>
                <p className="mx-auto mt-1 max-w-md text-sm leading-6 text-slate-500">Choose a date when the section has active enrolments to start taking attendance.</p>
            </div>
        );
    }

    return (
        <form onSubmit={save} aria-label="Attendance register" className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div className="flex flex-col gap-5 border-b border-slate-100 bg-slate-50/70 px-5 py-5 sm:px-6 lg:flex-row lg:items-center lg:justify-between">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <h2 className="text-sm font-semibold text-slate-900">Attendance overview</h2>
                        <span className="rounded-full bg-white px-2.5 py-1 text-xs font-medium text-slate-500 ring-1 ring-inset ring-slate-200">{students.length} {students.length === 1 ? 'student' : 'students'}</span>
                    </div>
                    <div className="mt-3 flex flex-wrap gap-2 text-xs">
                        {STATUSES.map(([value, label]) => (
                            <span key={value} className={`rounded-full px-3 py-1.5 font-semibold ${statusTone(value)}`}>
                                {label} <span className="ml-1 tabular-nums">{counts[value] || 0}</span>
                            </span>
                        ))}
                    </div>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <span className="mr-1 text-xs font-semibold text-slate-500">Mark everyone:</span>
                    <button
                        type="button"
                        onClick={() => markAll('present')}
                        className="rounded-lg border border-emerald-200 bg-white px-3 py-2 text-xs font-semibold text-emerald-800 transition hover:bg-emerald-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2"
                    >
                        Present
                    </button>
                    <button
                        type="button"
                        onClick={() => markAll('absent')}
                        className="rounded-lg border border-rose-200 bg-white px-3 py-2 text-xs font-semibold text-rose-800 transition hover:bg-rose-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-600 focus-visible:ring-offset-2"
                    >
                        Absent
                    </button>
                </div>
            </div>

            {error && (
                <div role="alert" className="mx-5 mt-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 sm:mx-6">
                    <p className="font-semibold">Please review the attendance details</p>
                    <p className="mt-1">{error}</p>
                </div>
            )}

            <ul className="divide-y divide-slate-100 px-4 sm:px-6">
                {students.map((student, index) => {
                    const record = form.data.records[index];
                    return (
                        <li key={student.id} className="grid gap-4 py-5 transition-colors hover:bg-slate-50/70 sm:px-2 lg:grid-cols-[minmax(0,1fr)_auto_minmax(0,16rem)] lg:items-center lg:gap-6">
                            <div className="flex min-w-0 items-center gap-3">
                                <span className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-xs font-semibold tabular-nums text-slate-500">
                                    {String(index + 1).padStart(2, '0')}
                                </span>
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-semibold text-slate-900">{student.name}</p>
                                    <p className="mt-0.5 truncate text-xs text-slate-500">Admission no. {student.admissionNumber}</p>
                                </div>
                            </div>
                            <div className="space-y-2">
                                <p className="text-xs font-medium text-slate-500">Attendance status</p>
                                <div role="radiogroup" aria-label={`Attendance status for ${student.name}`} className="flex flex-wrap gap-1.5">
                                    {STATUSES.map(([value, label]) => (
                                        <button
                                            key={value}
                                            type="button"
                                            role="radio"
                                            aria-checked={record.status === value}
                                            onClick={() => setRecord(index, 'status', value)}
                                            className={`rounded-lg border px-3 py-1.5 text-xs font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-600 focus-visible:ring-offset-2 ${record.status === value ? `border-transparent ${statusTone(value)} ring-1 ring-current/20` : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-50'}`}
                                        >
                                            {label}
                                        </button>
                                    ))}
                                </div>
                            </div>
                            <label className="block space-y-2">
                                <span className="text-xs font-medium text-slate-500">Remarks</span>
                                <input
                                    aria-label={`Remarks for ${student.name}`}
                                    value={record.remarks}
                                    onChange={(event) => setRecord(index, 'remarks', event.target.value)}
                                    placeholder="Add a note (optional)"
                                    maxLength={2000}
                                    className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-teal-500 focus:ring-2 focus:ring-teal-100"
                                />
                                {form.errors[`records.${index}.status`] && <p role="alert" className="text-xs font-medium text-rose-700">{form.errors[`records.${index}.status`]}</p>}
                                {form.errors[`records.${index}.remarks`] && <p role="alert" className="text-xs font-medium text-rose-700">{form.errors[`records.${index}.remarks`]}</p>}
                            </label>
                        </li>
                    );
                })}
            </ul>

            <div className="flex flex-col-reverse gap-3 border-t border-slate-100 bg-slate-50/50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <p className="text-xs text-slate-500">Review each student before saving this register.</p>
                <div className="flex flex-col-reverse gap-2 sm:flex-row">
                    <Link href={cancelHref} className="inline-flex justify-center rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-600 focus-visible:ring-offset-2">Cancel</Link>
                    <button disabled={form.processing} className="inline-flex justify-center rounded-xl bg-teal-800 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-600 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60">
                        {form.processing ? 'Saving…' : isEditing ? 'Update attendance' : 'Save attendance'}
                    </button>
                </div>
            </div>
        </form>
    );
}