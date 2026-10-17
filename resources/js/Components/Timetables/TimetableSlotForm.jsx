import { Link, useForm } from '@inertiajs/react';
import { Field, inputClass } from '../../Pages/Auth/Login';

/**
 * Shared by Timetable/Create (pass academicYearId) and Timetable/Edit (pass slot).
 */
export default function TimetableSlotForm({ assignments, days, slot = null, academicYearId = null, cancelHref }) {
    const form = useForm({
        ...(slot ? {} : { academic_year_id: academicYearId || '' }),
        teacher_subject_assignment_id: slot ? String(slot.teacherSubjectAssignmentId) : '',
        day_of_week: slot ? String(slot.dayOfWeek) : '1',
        starts_at: slot?.startsAt || '08:00',
        ends_at: slot?.endsAt || '08:45',
        room: slot?.room || '',
    });

    function save(event) {
        event.preventDefault();
        if (slot) {
            form.put(`/timetable/${slot.id}`, { preserveScroll: true });
        } else {
            form.post('/timetable', { preserveScroll: true });
        }
    }

    return (
        <form onSubmit={save} className="grid gap-4 sm:grid-cols-2">
            <div className="sm:col-span-2">
                <Field label="Class, section, subject and teacher" error={form.errors.teacher_subject_assignment_id}>
                    <select value={form.data.teacher_subject_assignment_id} onChange={(event) => form.setData('teacher_subject_assignment_id', event.target.value)} className={inputClass(form.errors.teacher_subject_assignment_id)} required>
                        <option value="">Select teaching assignment</option>
                        {assignments.map((assignment) => <option key={assignment.id} value={assignment.id}>{assignment.label}</option>)}
                    </select>
                </Field>
            </div>
            <Field label="Day" error={form.errors.day_of_week}>
                <select value={form.data.day_of_week} onChange={(event) => form.setData('day_of_week', event.target.value)} className={inputClass(form.errors.day_of_week)}>
                    {Object.entries(days).map(([value, name]) => <option key={value} value={value}>{name}</option>)}
                </select>
            </Field>
            <Field label="Room (optional)" error={form.errors.room}>
                <input value={form.data.room} onChange={(event) => form.setData('room', event.target.value)} className={inputClass(form.errors.room)} maxLength={100} />
            </Field>
            <Field label="Starts" error={form.errors.starts_at}>
                <input type="time" value={form.data.starts_at} onChange={(event) => form.setData('starts_at', event.target.value)} className={inputClass(form.errors.starts_at)} required />
            </Field>
            <Field label="Ends" error={form.errors.ends_at}>
                <input type="time" value={form.data.ends_at} onChange={(event) => form.setData('ends_at', event.target.value)} className={inputClass(form.errors.ends_at)} required />
            </Field>
            {(form.errors.section_id || form.errors.academic_year_id) && (
                <p role="alert" className="text-sm text-rose-700 sm:col-span-2">{form.errors.section_id || form.errors.academic_year_id}</p>
            )}
            <div className="flex flex-wrap gap-3 sm:col-span-2">
                <button type="submit" disabled={form.processing} className="rounded-xl bg-teal-800 px-5 py-3 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-60">
                    {form.processing ? 'Saving…' : slot ? 'Save period' : 'Add period'}
                </button>
                <Link href={cancelHref} className="rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</Link>
            </div>
        </form>
    );
}