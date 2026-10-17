import { Head, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { inputClass } from '../Auth/Login';

const summaryCards = [
    { key: 'markedDays', label: 'Marked student-days' },
    { key: 'present', label: 'Present' },
    { key: 'absent', label: 'Absent' },
    { key: 'late', label: 'Late' },
    { key: 'excused', label: 'Excused' },
];

function studentName(student) {
    return student.name;
}

export default function AttendanceReport({
    academicYears,
    selectedAcademicYearId,
    selectedSectionId,
    sections,
    summary,
    students,
    canExport,
}) {
    const exportParams = new URLSearchParams();
    if (selectedAcademicYearId) {
        exportParams.set('academic_year_id', selectedAcademicYearId);
    }
    if (selectedSectionId) {
        exportParams.set('section_id', selectedSectionId);
    }
    const exportUrl = `/reports/attendance/export?${exportParams.toString()}`;

    return (
        <AppLayout
            title="Attendance report"
            description="Review recorded attendance by academic year, class, and section."
        >
            <Head title="Attendance report" />

            <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <form method="GET" action="/reports/attendance" className="grid gap-4 sm:grid-cols-2">
                        <label className="grid gap-1.5 text-sm font-medium text-slate-700">
                            Academic year
                            <select name="academic_year_id" defaultValue={selectedAcademicYearId || ''} className={inputClass(false)}>
                                <option value="">Select an academic year</option>
                                {academicYears.map((year) => (
                                    <option key={year.id} value={year.id}>{year.name}</option>
                                ))}
                            </select>
                        </label>
                        <label className="grid gap-1.5 text-sm font-medium text-slate-700">
                            Class and section
                            <select name="section_id" defaultValue={selectedSectionId || ''} className={inputClass(false)}>
                                <option value="">All assigned sections</option>
                                {sections.map((section) => (
                                    <option key={section.id} value={section.id}>{section.name}</option>
                                ))}
                            </select>
                        </label>
                        <div className="flex items-center gap-2 sm:col-span-2">
                            <button type="submit" className="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-800">
                                Apply filters
                            </button>
                            {canExport && selectedAcademicYearId && (
                                <a href={exportUrl} className="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                    Download CSV
                                </a>
                            )}
                        </div>
                    </form>
                    <p className="text-sm text-slate-500">{students.total.toLocaleString()} students with recorded attendance</p>
                </div>
            </section>

            <section aria-label="Attendance totals" className="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                {summaryCards.map((card) => (
                    <article key={card.key} className="rounded-2xl border border-slate-200 bg-white p-4">
                        <p className="text-sm font-medium text-slate-500">{card.label}</p>
                        <p className="mt-2 text-2xl font-bold tracking-tight text-slate-950">
                            {summary[card.key].toLocaleString()}
                        </p>
                    </article>
                ))}
            </section>

            <section className="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div className="flex flex-col gap-1 border-b border-slate-100 px-5 py-4 sm:px-6">
                    <h2 className="font-semibold text-slate-900">Student attendance</h2>
                    <p className="text-sm text-slate-500">Counts include recorded attendance sessions in the selected academic year.</p>
                </div>
                {students.data.length === 0 ? (
                    <div className="px-6 py-12 text-center">
                        <p className="font-medium text-slate-800">No attendance records found</p>
                        <p className="mt-1 text-sm text-slate-500">Choose another year or section, or record attendance first.</p>
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-100 text-left text-sm">
                            <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th className="px-5 py-3 font-semibold">Student</th>
                                    <th className="px-5 py-3 font-semibold">Class / section</th>
                                    <th className="px-5 py-3 text-right font-semibold">Days</th>
                                    <th className="px-5 py-3 text-right font-semibold">Present</th>
                                    <th className="px-5 py-3 text-right font-semibold">Absent</th>
                                    <th className="px-5 py-3 text-right font-semibold">Late</th>
                                    <th className="px-5 py-3 text-right font-semibold">Excused</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {students.data.map((student) => (
                                    <tr key={student.id} className="hover:bg-slate-50/70">
                                        <td className="whitespace-nowrap px-5 py-3.5">
                                            <p className="font-medium text-slate-900">{studentName(student)}</p>
                                            <p className="mt-0.5 text-xs text-slate-500">{student.admissionNumber}</p>
                                        </td>
                                        <td className="whitespace-nowrap px-5 py-3.5 text-slate-600">
                                            {student.className} · {student.sectionName}
                                        </td>
                                        <td className="px-5 py-3.5 text-right font-medium text-slate-700">{student.markedDays}</td>
                                        <td className="px-5 py-3.5 text-right text-emerald-700">{student.present}</td>
                                        <td className="px-5 py-3.5 text-right text-rose-700">{student.absent}</td>
                                        <td className="px-5 py-3.5 text-right text-amber-700">{student.late}</td>
                                        <td className="px-5 py-3.5 text-right text-slate-600">{student.excused}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                {students.links.length > 3 && (
                    <nav aria-label="Attendance report pages" className="flex flex-wrap justify-between gap-2 border-t border-slate-100 px-5 py-4">
                        {students.links.map((link) => (
                            <button
                                key={link.label}
                                type="button"
                                disabled={!link.url || link.active}
                                onClick={() => link.url && router.get(link.url, {}, { preserveScroll: true })}
                                aria-current={link.active ? 'page' : undefined}
                                className={`rounded-lg px-3 py-2 text-sm ${link.active ? 'bg-teal-50 font-semibold text-teal-800' : 'text-slate-600'} disabled:opacity-40`}
                            >
                                {link.label.replace('&laquo;', '‹').replace('&raquo;', '›')}
                            </button>
                        ))}
                    </nav>
                )}
            </section>
        </AppLayout>
    );
}
