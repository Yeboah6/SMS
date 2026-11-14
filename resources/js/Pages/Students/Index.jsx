import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import PageAlerts from '../../Components/Exams/PageAlerts';
import Pagination from '../../Components/Pagination';

export default function StudentsIndex({ students, search, canCreate, canPromote, canImport, canExport }) {
    const [query, setQuery] = useState(search || '');

    function searchStudents(event) {
        event.preventDefault();
        router.get('/students', { search: query }, { preserveState: true, replace: true });
    }

    function deleteStudent(student) {
        if (window.confirm(`Delete ${student.name}? Students with enrolment, attendance, results or fee history cannot be deleted; mark them inactive instead.`)) {
            router.delete(`/students/${student.id}`, { preserveScroll: true });
        }
    }

    function exportUrl() {
        if (!search) return '/students/export';
        return `/students/export?search=${encodeURIComponent(search)}`;
    }

    return (
        <AppLayout title="Students" description="Permanent student profiles, guardian contacts, and academic enrolment history.">
            <Head title="Students" />
            <PageAlerts />
            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div className="flex flex-col gap-4 border-b border-slate-100 px-4 py-4 sm:px-5 sm:py-5 lg:flex-row lg:items-center lg:justify-between">
                    <div className="min-w-0">
                        <h2 className="font-semibold text-slate-900">Student directory</h2>
                        <p className="mt-1 text-sm text-slate-500">{students.total.toLocaleString()} profiles</p>
                    </div>
                    <div className="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:justify-end lg:max-w-3xl">
                        <form onSubmit={searchStudents} className="col-span-2 flex min-w-0 gap-2 sm:w-full lg:col-span-1 lg:w-auto">
                            <input type="search" aria-label="Search students" value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Name, admission no., guardian" className="min-w-0 flex-1 rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-teal-700 focus:outline-none sm:w-64 sm:flex-none" />
                            <button className="shrink-0 rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Search</button>
                        </form>
                        {canPromote && <Link href="/promotions" className="rounded-xl border border-teal-800 px-3 py-2.5 text-center text-sm font-semibold text-teal-800 hover:bg-teal-50 sm:px-4">Promotions</Link>}
                        {canImport && <Link href="/students/import" className="rounded-xl border border-slate-200 px-3 py-2.5 text-center text-sm font-semibold text-slate-700 hover:bg-slate-50 sm:px-4">Import CSV</Link>}
                        {canExport && <a href={exportUrl()} className="rounded-xl border border-slate-200 px-3 py-2.5 text-center text-sm font-semibold text-slate-700 hover:bg-slate-50 sm:px-4">Export CSV</a>}
                        {canCreate && <Link href="/students/create" className="rounded-xl bg-teal-800 px-3 py-2.5 text-center text-sm font-semibold text-white hover:bg-teal-900 sm:px-4">Add student</Link>}
                    </div>
                </div>
                {students.data.length === 0 ? (
                    <div className="px-4 py-14 text-center sm:px-6">
                        <p className="font-medium text-slate-800">No students found</p>
                        <p className="mt-1 text-sm text-slate-500">Adjust the search or create a student profile.</p>
                    </div>
                ) : (
                    <>
                        <div className="divide-y divide-slate-100 xl:hidden">
                            {students.data.map((student) => (
                                <article key={student.id} className="space-y-4 p-4 sm:p-5">
                                    <div className="flex min-w-0 items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <Link href={`/students/${student.id}`} className="break-words font-semibold text-teal-800 hover:text-teal-950">{student.name}</Link>
                                            <p className="mt-1 break-all text-xs text-slate-500">{student.admissionNumber}</p>
                                        </div>
                                        <span className="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold capitalize text-slate-700">{student.status}</span>
                                    </div>
                                    <dl className="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                                        <div className="min-w-0">
                                            <dt className="text-xs font-medium text-slate-500">Guardian</dt>
                                            <dd className="mt-1 break-words text-slate-700">
                                                {student.guardians.length
                                                    ? <>{student.guardians[0].name}<span className="block break-all text-xs text-slate-500">{student.guardians[0].phone}</span></>
                                                    : '—'}
                                            </dd>
                                        </div>
                                        <div className="min-w-0">
                                            <dt className="text-xs font-medium text-slate-500">Current enrolment</dt>
                                            <dd className="mt-1 break-words text-slate-700">{student.enrolment ? `${student.enrolment.class} · ${student.enrolment.section}` : 'Not enrolled'}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-xs font-medium text-slate-500">Admission date</dt>
                                            <dd className="mt-1 text-slate-700">{student.admissionDate}</dd>
                                        </div>
                                    </dl>
                                    <div className="flex flex-wrap gap-2 border-t border-slate-100 pt-3 text-sm font-semibold">
                                        <Link href={`/students/${student.id}`} className="rounded-lg border border-slate-200 px-3 py-2 text-teal-800 hover:bg-slate-50">View</Link>
                                        {student.canEdit && <Link href={`/students/${student.id}/edit`} className="rounded-lg border border-slate-200 px-3 py-2 text-teal-800 hover:bg-slate-50">Edit</Link>}
                                        {student.canDelete && <button type="button" onClick={() => deleteStudent(student)} className="rounded-lg border border-rose-200 px-3 py-2 text-rose-700 hover:bg-rose-50">Delete</button>}
                                    </div>
                                </article>
                            ))}
                        </div>
                        <div className="hidden overflow-x-auto xl:block">
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
                    </>
                )}
                <Pagination links={students.links} from={students.from} to={students.to} total={students.total} />
            </section>
        </AppLayout>
    );
}