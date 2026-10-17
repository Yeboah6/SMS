import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import PageAlerts from '../../Components/Exams/PageAlerts';
import { STATUSES, dateLabel, statusLabel, statusTone } from '../../lib/attendance';

export default function AttendanceShow({ register, records, counts, can }) {
    function deleteRegister() {
        if (window.confirm(`Delete the ${register.className} ${register.sectionName} register for ${register.date}? All of its marks are removed.`)) {
            router.delete(`/attendance/${register.id}`);
        }
    }

    return (
        <AppLayout title={`${register.className} · ${register.sectionName}`} description={`${dateLabel(register.date)} · ${register.academicYear}`}>
            <Head title="Attendance register" />
            <PageAlerts />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <Link href={`/attendance?academic_year_id=${register.academicYearId}&date=${register.date}`} className="text-sm font-semibold text-teal-800 hover:text-teal-950">Back to attendance</Link>
                <div className="flex items-center gap-4 text-sm font-semibold">
                    {can.update && <Link href={`/attendance/${register.id}/edit`} className="text-teal-800 hover:text-teal-950">Edit register</Link>}
                    {can.delete && <button type="button" onClick={deleteRegister} className="text-rose-700 hover:text-rose-900">Delete register</button>}
                </div>
            </div>

            <section className="mb-6 grid gap-3 sm:grid-cols-4">
                {STATUSES.map(([value, label]) => (
                    <article key={value} className="rounded-2xl border border-slate-200 bg-white p-5">
                        <p className="text-sm text-slate-500">{label}</p>
                        <p className="mt-2 text-2xl font-semibold tracking-tight text-slate-900">{counts[value] || 0}</p>
                    </article>
                ))}
            </section>

            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div className="border-b border-slate-100 px-5 py-4"><h2 className="font-semibold text-slate-900">{records.length} students</h2></div>
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[560px] text-left text-sm">
                        <thead className="bg-slate-50 text-xs text-slate-500"><tr><th className="px-5 py-3">Student</th><th className="px-5 py-3">Status</th><th className="px-5 py-3">Remarks</th></tr></thead>
                        <tbody className="divide-y divide-slate-100">
                            {records.map((record) => (
                                <tr key={record.id}>
                                    <td className="px-5 py-3.5"><Link href={`/students/${record.studentId}`} className="font-medium text-slate-900 hover:text-teal-800">{record.name}</Link><span className="block text-xs text-slate-500">{record.admissionNumber}</span></td>
                                    <td className="px-5 py-3.5"><span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${statusTone(record.status)}`}>{statusLabel(record.status)}</span></td>
                                    <td className="px-5 py-3.5 text-slate-600">{record.remarks || '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </section>
        </AppLayout>
    );
}