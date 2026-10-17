import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import PageAlerts from '../../Components/Exams/PageAlerts';

export default function StudentsIndex({ students, search, canCreate, canPromote, canImport }) {
    const [query, setQuery] = useState(search || '');

    function searchStudents(event) {
        event.preventDefault();
        router.get('/students', { search: query }, { preserveState: true, replace: true });
    }

    function changePage(page) {
        router.get('/students', { search: query, page }, { preserveState: true, replace: true });
    }

    function deleteStudent(student) {
        if (window.confirm(`Delete ${student.name}? Students with enrolment, attendance, results or fee history cannot be deleted; mark them inactive instead.`)) {
            router.delete(`/students/${student.id}`, { preserveScroll: true });
        }
    }

    return (
        <AppLayout title="Students" description="Permanent student profiles, guardian contacts, and academic enrolment history.">
            <Head title="Students" />
            <PageAlerts />
            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div className="flex flex-col gap-4 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 className="font-semibold text-slate-900">Student directory</h2>
                        <p className="mt-1 text-sm text-slate-500">{students.total.toLocaleString()} profiles</p>
                    </div>
                    <div className="flex flex-col gap-2 sm:flex-row">
                        {canPromote && <Link href="/promotions" className="rounded-xl border border-teal-800 px-4 py-2.5 text-center text-sm font-semibold text-teal-800 hover:bg-teal-50">Promotions</Link>}
                        <form onSubmit={searchStudents} className="flex gap-2">
                            <input type="search" aria-label="Search students" value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Name, admission no., guardian" className="min-w-0 rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-teal-700 focus:outline-none" />
                            <button className="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Search</button>
                        </form>
                        {canImport && <Link href="/students/import" className="rounded-xl border border-slate-200 px-4 py-2.5 text-center text-sm font-semibold text-slate-700 hover:bg-slate-50">Import CSV</Link>}
                        {canCreate && <Link href="/students/create" className="rounded-xl bg-teal-800 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-teal-900">Add student</Link>}
                    </div>
                </div>
                {students.data.length === 0 ? (
                    <div className="px-6 py-14 text-center">
                        <p className="font-medium text-slate-800">No students found</p>
                        <p className="mt-1 text-sm text-slate-500">Adjust the search or create a student profile.</p>
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[820px] text-left text-sm">
                            <thead className="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th className="px-5 py-3">Student</th>
                                    <th className="px-5 py-3">Guardian</th>
                                    <th className="px-5 py-3">Current enrolment</th>
                                    <th className="px-5 py-3">Admission date</th>
                                    <th className="px-5 py-3">Status</th>
                                    <th className="px-5 py-3"><span className="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {students.data.map((student) => (
                                    <tr key={student.id} className="hover:bg-slate-50/70">
                                        <td className="px-5 py-4">
                                            <Link href={`/students/${student.id}`} className="font-medium text-teal-800 hover:text-teal-950">{student.name}</Link>
                                            <p className="mt-0.5 text-xs text-slate-500">{student.admissionNumber}</p>
                                        </td>
                                        <td className="px-5 py-4 text-slate-600">
                                            {student.guardians.length
                                                ? <>{student.guardians[0].name}<span className="block text-xs text-slate-500">{student.guardians[0].phone}</span></>
                                                : '—'}
                                        </td>
                                        <td className="px-5 py-4 text-slate-600">{student.enrolment ? `${student.enrolment.class} · ${student.enrolment.section}` : 'Not enrolled'}</td>
                                        <td className="px-5 py-4 text-slate-600">{student.admissionDate}</td>
                                        <td className="px-5 py-4"><span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold capitalize text-slate-700">{student.status}</span></td>
                                        <td className="px-5 py-4">
                                            <div className="flex items-center justify-end gap-3 text-xs font-semibold">
                                                <Link href={`/students/${student.id}`} className="text-teal-800 hover:text-teal-950">View</Link>
                                                {student.canEdit && <Link href={`/students/${student.id}/edit`} className="text-teal-800 hover:text-teal-950">Edit</Link>}
                                                {student.canDelete && <button type="button" onClick={() => deleteStudent(student)} className="text-rose-700 hover:text-rose-900">Delete</button>}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                {students.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-slate-100 px-5 py-3">
                        <button type="button" disabled={students.current_page <= 1} onClick={() => changePage(students.current_page - 1)} className="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 disabled:opacity-40">Previous</button>
                        <span className="text-xs text-slate-500">Page {students.current_page} of {students.last_page}</span>
                        <button type="button" disabled={students.current_page >= students.last_page} onClick={() => changePage(students.current_page + 1)} className="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 disabled:opacity-40">Next</button>
                    </div>
                )}
            </section>
        </AppLayout>
    );
}