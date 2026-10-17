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
            <div className="rounded-2xl border border-slate-200 bg-white px-6 py-14 text-center">
                <p className="font-medium text-slate-800">No students enrolled for this date</p>
                <p className="mt-1 text-sm text-slate-500">Choose a date when the section has active enrolments.</p>
            </div>
        );
    }

    return (
        <form onSubmit={save} className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <div className="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex flex-wrap gap-2 text-xs">
                    {STATUSES.map(([value, label]) => <span key={value} className={`rounded-full px-2.5 py-1 font-semibold ${statusTone(value)}`}>{label}: {counts[value] || 0}</span>)}
                </div>
                <div className="flex flex-wrap items-center gap-2 text-xs font-semibold">
                    <span className="text-slate-500">Mark everyone:</span>
                    <button type="button" onClick={() => markAll('present')} className="rounded-lg border border-slate-200 px-3 py-1.5 text-slate-700 hover:bg-slate-50">Present</button>
                    <button type="button" onClick={() => markAll('absent')} className="rounded-lg border border-slate-200 px-3 py-1.5 text-slate-700 hover:bg-slate-50">Absent</button>
                </div>
            </div>

            {error && <div role="alert" className="mx-5 mt-4 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700">{error}</div>}

            <ul className="divide-y divide-slate-100">
                {students.map((student, index) => {
                    const record = form.data.records[index];
                    return (
                        <li key={student.id} className="grid gap-3 px-5 py-4 lg:grid-cols-[minmax(0,1fr)_auto_minmax(0,16rem)] lg:items-center">
                            <div>
                                <p className="font-medium text-slate-900">{student.name}</p>
                                <p className="mt-0.5 text-xs text-slate-500">{student.admissionNumber}</p>
                            </div>
                            <div role="radiogroup" aria-label={`Attendance status for ${student.name}`} className="flex flex-wrap gap-1.5">
                                {STATUSES.map(([value, label]) => (
                                    <button
                                        key={value}
                                        type="button"
                                        role="radio"
                                        aria-checked={record.status === value}
                                        onClick={() => setRecord(index, 'status', value)}
                                        className={`rounded-lg border px-3 py-1.5 text-xs font-semibold ${record.status === value ? `border-transparent ${statusTone(value)} ring-2 ring-slate-300` : 'border-slate-200 text-slate-600 hover:bg-slate-50'}`}
                                    >
                                        {label}
                                    </button>
                                ))}
                            </div>
                            <div>
                                <input aria-label={`Remarks for ${student.name}`} value={record.remarks} onChange={(event) => setRecord(index, 'remarks', event.target.value)} placeholder="Remarks" maxLength={2000} className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                                {form.errors[`records.${index}.status`] && <p role="alert" className="mt-1 text-xs text-rose-700">{form.errors[`records.${index}.status`]}</p>}
                                {form.errors[`records.${index}.remarks`] && <p role="alert" className="mt-1 text-xs text-rose-700">{form.errors[`records.${index}.remarks`]}</p>}
                            </div>
                        </li>
                    );
                })}
            </ul>

            <div className="flex flex-wrap justify-end gap-3 border-t border-slate-100 px-5 py-4">
                <Link href={cancelHref} className="rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</Link>
                <button disabled={form.processing} className="rounded-xl bg-teal-800 px-5 py-3 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-60">
                    {form.processing ? 'Saving…' : isEditing ? 'Update attendance' : 'Save attendance'}
                </button>
            </div>
        </form>
    );
}