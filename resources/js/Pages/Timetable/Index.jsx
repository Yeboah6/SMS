import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import StatusBanner from '../../Components/StatusBanner';
import { Field, inputClass } from '../Auth/Login';

export default function TimetableIndex({ mode, years, selectedAcademicYearId, selectedStudentId, students, slots, canManage, days }) {
    const isPortal = mode === 'student' || mode === 'parent';
    const selectedYear = years.find((year) => year.id === selectedAcademicYearId);

    function selectYear(academicYearId) {
        router.get('/timetable', academicYearId ? { academic_year_id: academicYearId } : {}, { preserveScroll: true });
    }

    function selectStudent(studentId) {
        router.get('/timetable', studentId ? { student_id: studentId } : {}, { preserveScroll: true });
    }

    function removeSlot(slot) {
        if (window.confirm(`Remove ${slot.subject} (${slot.startsAt}–${slot.endsAt}) from ${slot.dayName}?`)) {
            router.delete(`/timetable/${slot.id}`, { preserveScroll: true });
        }
    }

    return (
        <AppLayout title="Timetable" description={isPortal ? 'View the current class schedule.' : 'Manage and review academic-year class schedules.'}>
            <Head title="Timetable" />
            <StatusBanner />

            <section className="mb-6 flex flex-wrap items-end justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-5">
                <div>
                    <h2 className="font-semibold text-slate-900">{isPortal ? 'Class schedule' : 'Academic schedule'}</h2>
                    <p className="mt-1 text-sm text-slate-500">{selectedYear?.name || 'No academic year selected'}{selectedYear?.status === 'locked' ? ' · Locked history' : ''}</p>
                </div>
                <div className="flex flex-wrap items-end gap-3">
                    {(mode === 'manager' || mode === 'teacher') && (
                        <Field label="Academic year">
                            <select value={selectedAcademicYearId || ''} onChange={(event) => selectYear(event.target.value)} className={inputClass(false)}>
                                {years.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                            </select>
                        </Field>
                    )}
                    {mode === 'parent' && (
                        <Field label="Linked student">
                            <select value={selectedStudentId || ''} onChange={(event) => selectStudent(event.target.value)} className={inputClass(false)}>
                                <option value="">All linked students</option>
                                {students.map((student) => <option key={student.id} value={student.id}>{student.name}</option>)}
                            </select>
                        </Field>
                    )}
                    {canManage && (
                        <Link href={`/timetable/create?academic_year_id=${selectedAcademicYearId}`} className="rounded-xl bg-teal-800 px-4 py-3 text-sm font-semibold text-white hover:bg-teal-900">Add period</Link>
                    )}
                </div>
            </section>

            <section className="grid gap-4">
                <div>
                    <h2 className="font-semibold text-slate-900">{mode === 'teacher' ? 'My teaching schedule' : 'Periods'}</h2>
                    <p className="mt-1 text-sm text-slate-500">{slots.total} period{slots.total === 1 ? '' : 's'}</p>
                </div>

                {slots.data.length === 0 ? (
                    <div className="rounded-2xl border border-slate-200 bg-white px-6 py-10 text-center text-sm text-slate-500">No timetable periods are available for this selection.</div>
                ) : (
                    <div className="grid gap-5">
                        {Object.entries(days).map(([day, name]) => {
                            const daySlots = slots.data.filter((slot) => String(slot.dayOfWeek) === day);
                            if (daySlots.length === 0) return null;

                            return (
                                <div key={day} className="grid gap-3">
                                    <h3 className="text-sm font-semibold text-slate-500">{name}</h3>
                                    <div className="grid gap-3 xl:grid-cols-2">
                                        {daySlots.map((slot) => (
                                            <article key={slot.id} className="rounded-xl border border-slate-200 bg-white p-4">
                                                <div className="flex flex-wrap items-start justify-between gap-3">
                                                    <div>
                                                        <Link href={`/timetable/${slot.id}`} className="font-semibold text-slate-900 hover:text-teal-800">{slot.startsAt}–{slot.endsAt} · {slot.subject}</Link>
                                                        <p className="mt-1 text-sm text-slate-600">{slot.section} · {slot.teacher}</p>
                                                        {slot.room && <p className="mt-1 text-xs text-slate-500">Room {slot.room}</p>}
                                                    </div>
                                                    <div className="flex items-center gap-3 text-xs font-semibold">
                                                        <Link href={`/timetable/${slot.id}`} className="text-teal-800 hover:text-teal-950">View</Link>
                                                        {canManage && <Link href={`/timetable/${slot.id}/edit`} className="text-teal-800 hover:text-teal-950">Edit</Link>}
                                                        {canManage && <button type="button" onClick={() => removeSlot(slot)} className="text-rose-700 hover:text-rose-900">Remove</button>}
                                                    </div>
                                                </div>
                                            </article>
                                        ))}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}

                {slots.links.length > 3 && (
                    <nav aria-label="Timetable pages" className="flex flex-wrap gap-2">
                        {slots.links.map((link, index) => (
                            <Link
                                key={`${link.label}-${index}`}
                                href={link.url || '#'}
                                preserveScroll
                                className={`rounded-lg px-3 py-2 text-sm ${link.active ? 'bg-teal-700 text-white' : 'border border-slate-200 bg-white text-slate-700'} ${!link.url ? 'pointer-events-none opacity-50' : ''}`}
                            >
                                {link.label.replace('&laquo;', '‹').replace('&raquo;', '›')}
                            </Link>
                        ))}
                    </nav>
                )}
            </section>
        </AppLayout>
    );
}