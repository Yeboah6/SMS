import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import StatusBanner from '../../Components/StatusBanner';

export default function SubjectsShow({ subject, classes, assignments, can }) {
    function deleteSubject() {
        if (window.confirm(`Delete ${subject.name}? Subjects assigned to classes or teachers cannot be deleted.`)) {
            router.delete(`/subjects/${subject.id}`);
        }
    }

    return (
        <AppLayout title={subject.name} description={`Subject code ${subject.code}`}>
            <Head title={subject.name} />
            <StatusBanner />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <Link href="/subjects" className="text-sm font-semibold text-teal-800 hover:text-teal-950">Back to subjects</Link>
                <div className="flex items-center gap-4 text-sm font-semibold">
                    {can.update && <Link href={`/subjects/${subject.id}/edit`} className="text-teal-800 hover:text-teal-950">Edit subject</Link>}
                    {can.delete && <button type="button" onClick={deleteSubject} className="text-rose-700 hover:text-rose-900">Delete subject</button>}
                </div>
            </div>

            <section className="mb-6 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <h2 className="font-semibold text-slate-900">Details</h2>
                <dl className="mt-4 grid gap-4 text-sm sm:grid-cols-3">
                    <div>
                        <dt className="text-xs font-medium text-slate-500">Code</dt>
                        <dd className="mt-1 font-mono text-slate-900">{subject.code}</dd>
                    </div>
                    <div>
                        <dt className="text-xs font-medium text-slate-500">Name</dt>
                        <dd className="mt-1 text-slate-900">{subject.name}</dd>
                    </div>
                    <div className="sm:col-span-3">
                        <dt className="text-xs font-medium text-slate-500">Description</dt>
                        <dd className="mt-1 text-slate-700">{subject.description || 'No description.'}</dd>
                    </div>
                </dl>
            </section>

            <div className="grid gap-6 xl:grid-cols-2">
                {classes !== null && (
                    <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                        <h2 className="font-semibold text-slate-900">Classes using this subject</h2>
                        {classes.length === 0 ? (
                            <p className="mt-4 rounded-xl bg-slate-50 px-3 py-4 text-sm text-slate-500">Not assigned to any class.</p>
                        ) : (
                            <ul className="mt-4 divide-y divide-slate-100 rounded-xl border border-slate-100">
                                {classes.map((schoolClass) => (
                                    <li key={schoolClass.id} className="px-3 py-3 text-sm">
                                        <Link href={`/classes/${schoolClass.id}`} className="font-medium text-slate-900 hover:text-teal-800">{schoolClass.name}</Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                )}

                <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <h2 className="font-semibold text-slate-900">Teachers</h2>
                    {assignments.length === 0 ? (
                        <p className="mt-4 rounded-xl bg-slate-50 px-3 py-4 text-sm text-slate-500">No teachers assigned.</p>
                    ) : (
                        <ul className="mt-4 divide-y divide-slate-100 rounded-xl border border-slate-100">
                            {assignments.map((assignment) => (
                                <li key={assignment.id} className="flex flex-wrap items-center justify-between gap-2 px-3 py-3 text-sm">
                                    <span className="font-medium text-slate-900">{assignment.staffName}</span>
                                    <Link href={`/sections/${assignment.sectionId}`} className="text-xs font-semibold text-teal-800 hover:text-teal-950">{assignment.className} · Section {assignment.sectionName}</Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}