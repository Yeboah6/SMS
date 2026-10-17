import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';

export default function PortalAttendanceIndex({ mode, students, selectedStudentId, records }) {
    function selectStudent(event) {
        const studentId = event.target.value;
        router.get('/attendance', studentId ? { student_id: studentId } : {}, { preserveScroll: true, replace: true });
    }

    return (
        <AppLayout
            title={mode === 'student' ? 'My attendance' : 'Children’s attendance'}
            description="Review recorded attendance for your school account."
        >
            <Head title="Attendance" />
            <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h2 className="font-semibold text-slate-900">Attendance history</h2>
                        <p className="mt-1 text-sm text-slate-500">Only recorded attendance for {mode === 'student' ? 'your profile' : 'your linked children'} is shown.</p>
                    </div>
                    {mode === 'parent' && (
                        <label className="grid gap-1 text-xs font-medium text-slate-600">
                            Student
                            <select value={selectedStudentId || ''} onChange={selectStudent} className="min-w-56 rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                                <option value="">All linked children</option>
                                {students.map((student) => <option key={student.id} value={student.id}>{student.name}</option>)}
                            </select>
                        </label>
                    )}
                </div>

                {records.data.length === 0 ? (
                    <p className="mt-6 rounded-xl bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">No attendance records are available.</p>
                ) : (
                    <div className="mt-5 overflow-x-auto">
                        <table className="w-full min-w-[620px] text-left text-sm">
                            <thead className="border-b border-slate-100 text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    {mode === 'parent' && <th className="py-3 pr-4">Student</th>}
                                    <th className="py-3 pr-4">Date</th>
                                    <th className="py-3 pr-4">Academic year</th>
                                    <th className="py-3 pr-4">Class</th>
                                    <th className="py-3">Status</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {records.data.map((record) => (
                                    <tr key={record.id}>
                                        {mode === 'parent' && <td className="py-3 pr-4 font-medium text-slate-800">{record.studentName}</td>}
                                        <td className="py-3 pr-4 text-slate-600">{record.date}</td>
                                        <td className="py-3 pr-4 text-slate-600">{record.academicYear}</td>
                                        <td className="py-3 pr-4 text-slate-600">{record.class} · {record.section}</td>
                                        <td className="py-3 capitalize text-slate-700">{record.status}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {records.links.length > 3 && (
                    <nav aria-label="Attendance pages" className="mt-5 flex flex-wrap gap-2">
                        {records.links.map((link, index) => link.url ? (
                            <Link key={index} href={link.url} preserveScroll className={`rounded-lg border px-3 py-2 text-xs font-medium ${link.active ? 'border-teal-800 bg-teal-800 text-white' : 'border-slate-200 text-slate-600 hover:bg-slate-50'}`} dangerouslySetInnerHTML={{ __html: link.label }} />
                        ) : <span key={index} className="rounded-lg border border-slate-100 px-3 py-2 text-xs text-slate-400" dangerouslySetInnerHTML={{ __html: link.label }} />)}
                    </nav>
                )}
            </section>
        </AppLayout>
    );
}
