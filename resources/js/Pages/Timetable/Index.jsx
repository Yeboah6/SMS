import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import StatusBanner from '../../Components/StatusBanner';
import { Field, inputClass } from '../Auth/Login';

export default function TimetableIndex({ mode, years, selectedAcademicYearId, selectedStudentId, students, classes = [], selectedClassId, slots, canManage, days }) {
    const isPortal = mode === 'student' || mode === 'parent';
    const selectedYear = years.find((year) => year.id === selectedAcademicYearId);
    const selectedClass = classes.find((schoolClass) => schoolClass.id === selectedClassId);

    function applyFilters(overrides = {}) {
        const nextYearId = overrides.academicYearId !== undefined ? overrides.academicYearId : selectedAcademicYearId;
        const nextClassId = overrides.classId !== undefined ? overrides.classId : selectedClassId;
        const nextStudentId = overrides.studentId !== undefined ? overrides.studentId : selectedStudentId;

        const params = {};
        if (mode === 'manager' || mode === 'teacher') {
            if (nextYearId) params.academic_year_id = nextYearId;
            if (nextClassId) params.class_id = nextClassId;
        }
        if (mode === 'parent' && nextStudentId) {
            params.student_id = nextStudentId;
        }

        router.get('/timetable', params, { preserveScroll: true });
    }

    function removeSlot(slot) {
        if (window.confirm(`Remove ${slot.subject} (${slot.startsAt}–${slot.endsAt}) from ${slot.dayName}?`)) {
            router.delete(`/timetable/${slot.id}`, { preserveScroll: true });
        }
    }

    // Group: class -> section -> day -> periods
    const groupedByClass = (() => {
        const classMap = new Map();
        for (const slot of slots.data) {
            if (!classMap.has(slot.classId)) {
                classMap.set(slot.classId, { id: slot.classId, name: slot.className, sections: new Map() });
            }
            const classEntry = classMap.get(slot.classId);
            if (!classEntry.sections.has(slot.sectionId)) {
                classEntry.sections.set(slot.sectionId, { id: slot.sectionId, name: slot.sectionName, days: new Map() });
            }
            const sectionEntry = classEntry.sections.get(slot.sectionId);
            if (!sectionEntry.days.has(slot.dayOfWeek)) {
                sectionEntry.days.set(slot.dayOfWeek, []);
            }
            sectionEntry.days.get(slot.dayOfWeek).push(slot);
        }

        return Array.from(classMap.values())
            .map((classEntry) => ({
                ...classEntry,
                sections: Array.from(classEntry.sections.values())
                    .map((sectionEntry) => ({
                        ...sectionEntry,
                        days: Array.from(sectionEntry.days.entries()).sort(([a], [b]) => a - b),
                    }))
                    .sort((a, b) => a.name.localeCompare(b.name)),
            }))
            .sort((a, b) => a.name.localeCompare(b.name));
    })();

    return (
        <AppLayout title="Timetable" description={isPortal ? 'View the current class schedule.' : 'Manage and review academic-year class schedules.'}>
            <Head title="Timetable" />
            <StatusBanner />

            <section className="mb-6 flex flex-wrap items-end justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-5">
                <div>
                    <h2 className="font-semibold text-slate-900">{isPortal ? 'Class schedule' : 'Academic schedule'}</h2>
                    <p className="mt-1 text-sm text-slate-500">
                        {selectedYear?.name || 'No academic year selected'}
                        {selectedClass ? ` · ${selectedClass.name}` : ''}
                        {selectedYear?.status === 'locked' ? ' · Locked history' : ''}
                    </p>
                </div>
                <div className="flex flex-wrap items-end gap-3">
                    {(mode === 'manager' || mode === 'teacher') && (
                        <Field label="Academic year">
                            <select
                                value={selectedAcademicYearId || ''}
                                onChange={(event) => applyFilters({ academicYearId: event.target.value, classId: '' })}
                                className={inputClass(false)}
                            >
                                {years.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                            </select>
                        </Field>
                    )}
                    {(mode === 'manager' || mode === 'teacher') && classes.length > 0 && (
                        <Field label="Class">
                            <select
                                value={selectedClassId || ''}
                                onChange={(event) => applyFilters({ classId: event.target.value })}
                                className={inputClass(false)}
                            >
                                <option value="">All classes</option>
                                {classes.map((schoolClass) => (
                                    <option key={schoolClass.id} value={schoolClass.id}>{schoolClass.name}</option>
                                ))}
                            </select>
                        </Field>
                    )}
                    {mode === 'parent' && (
                        <Field label="Linked student">
                            <select
                                value={selectedStudentId || ''}
                                onChange={(event) => applyFilters({ studentId: event.target.value })}
                                className={inputClass(false)}
                            >
                                <option value="">All linked students</option>
                                {students.map((student) => <option key={student.id} value={student.id}>{student.name}</option>)}
                            </select>
                        </Field>
                    )}
                    {canManage && (
                        <Link
                            href={`/timetable/create?academic_year_id=${selectedAcademicYearId}${selectedClassId ? `&class_id=${selectedClassId}` : ''}`}
                            className="rounded-xl bg-teal-800 px-4 py-3 text-sm font-semibold text-white hover:bg-teal-900"
                        >
                            Add period
                        </Link>
                    )}
                </div>
            </section>

            <section className="grid gap-4">
                <div>
                    <h2 className="font-semibold text-slate-900">{mode === 'teacher' ? 'My teaching schedule' : 'Periods'}</h2>
                    <p className="mt-1 text-sm text-slate-500">{slots.total} period{slots.total === 1 ? '' : 's'}</p>
                </div>

                {slots.data.length === 0 ? (
                    <div className="rounded-2xl border border-slate-200 bg-white px-6 py-10 text-center text-sm text-slate-500">
                        No timetable periods are available for this selection.
                    </div>
                ) : (
                    <div className="grid gap-8">
                        {groupedByClass.map((classEntry) => {
                            const totalPeriods = classEntry.sections.reduce(
                                (sum, section) => sum + section.days.reduce((daySum, [, daySlots]) => daySum + daySlots.length, 0),
                                0,
                            );

                            return (
                                <section key={classEntry.id} className="grid gap-5">
                                    <div className="flex flex-wrap items-baseline justify-between gap-2 border-b border-slate-100 pb-3">
                                        <h2 className="text-lg font-semibold text-slate-900">{classEntry.name}</h2>
                                        <span className="text-xs text-slate-500">
                                            {classEntry.sections.length} section{classEntry.sections.length === 1 ? '' : 's'}
                                            {' · '}
                                            {totalPeriods} period{totalPeriods === 1 ? '' : 's'}
                                        </span>
                                    </div>

                                    {classEntry.sections.map((sectionEntry) => (
                                        <div key={sectionEntry.id} className="grid gap-4">
                                            <h3 className="text-sm font-semibold text-teal-800">Section {sectionEntry.name}</h3>
                                            {sectionEntry.days.map(([day, daySlots]) => (
                                                <div key={day} className="grid gap-3">
                                                    <h4 className="text-xs font-semibold uppercase tracking-wide text-slate-500">{days[day]}</h4>
                                                    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                                                        {daySlots.map((slot) => (
                                                            <article key={slot.id} className="rounded-xl border border-slate-200 bg-white p-4">
                                                                <div className="flex flex-wrap items-start justify-between gap-3">
                                                                    <div>
                                                                        <Link href={`/timetable/${slot.id}`} className="font-semibold text-slate-900 hover:text-teal-800">
                                                                            {slot.startsAt}–{slot.endsAt} · {slot.subject}
                                                                        </Link>
                                                                        <p className="mt-1 text-sm text-slate-600">{slot.teacher}</p>
                                                                        {slot.room && <p className="mt-1 text-xs text-slate-500">Room {slot.room}</p>}
                                                                    </div>
                                                                    <div className="flex items-center gap-3 text-xs font-semibold">
                                                                        <Link href={`/timetable/${slot.id}`} className="text-teal-800 hover:text-teal-950">View</Link>
                                                                        {canManage && <Link href={`/timetable/${slot.id}/edit`} className="text-teal-800 hover:text-teal-950">Edit</Link>}
                                                                        {canManage && (
                                                                            <button type="button" onClick={() => removeSlot(slot)} className="text-rose-700 hover:text-rose-900">
                                                                                Remove
                                                                            </button>
                                                                        )}
                                                                    </div>
                                                                </div>
                                                            </article>
                                                        ))}
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    ))}
                                </section>
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