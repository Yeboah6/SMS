import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import EmptyState from '../../Components/EmptyState';
import StatusBanner from '../../Components/StatusBanner';

export default function ClassesIndex({ years, selectedYearId, yearLocked, classes, can }) {
    const yearId = String(selectedYearId || '');

    function selectYear(event) {
        const nextYear = event.target.value;
        router.get('/classes', nextYear ? { academic_year_id: nextYear } : {}, { preserveState: true, replace: true });
    }

    function deleteClass(schoolClass) {
        if (window.confirm(`Delete ${schoolClass.name}? Classes with sections, subject mappings, or historical records cannot be deleted.`)) {
            router.delete(`/classes/${schoolClass.id}`, { preserveScroll: true });
        }
    }

    return (
        <AppLayout title="Classes & sections" description="Build a year-scoped class structure and assign subjects.">
            <Head title="Classes & sections" />
            <StatusBanner />

            <div className="mb-6 flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p className="text-sm font-semibold text-slate-800">Academic year scope</p>
                    <p className="mt-1 text-xs text-slate-500">Classes and sections remain attached to their historical year.</p>
                </div>
                <div className="flex flex-wrap items-center gap-3">
                    <select value={yearId} onChange={selectYear} aria-label="Select academic year" className="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800">
                        <option value="">Select an academic year</option>
                        {years.map((year) => <option key={year.id} value={year.id}>{year.name} · {year.status}</option>)}
                    </select>
                    {can.create && yearId && !yearLocked && (
                        <Link href={`/classes/create?academic_year_id=${yearId}`} className="rounded-xl bg-teal-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-900">New class</Link>
                    )}
                </div>
            </div>

            {!yearId ? (
                <EmptyState title="Select an academic year" detail="Choose a year above to view or create its class structure." />
            ) : classes.length === 0 ? (
                <EmptyState title="No classes for this year" detail="Create a class to start configuring sections and subjects.">
                    {can.create && !yearLocked && <Link href={`/classes/create?academic_year_id=${yearId}`} className="text-sm font-semibold text-teal-800 hover:text-teal-950">New class</Link>}
                </EmptyState>
            ) : (
                <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {classes.map((schoolClass) => (
                        <li key={schoolClass.id} className="flex flex-col rounded-2xl border border-slate-200 bg-white p-5">
                            <Link href={`/classes/${schoolClass.id}`} className="text-lg font-semibold text-slate-950 hover:text-teal-800">{schoolClass.name}</Link>
                            <dl className="mt-3 grid grid-cols-3 gap-2 text-center">
                                <Stat label="Sections" value={schoolClass.sectionsCount} />
                                <Stat label="Subjects" value={schoolClass.subjectsCount} />
                                <Stat label="Students" value={schoolClass.studentsCount} />
                            </dl>
                            <div className="mt-4 flex items-center gap-4 border-t border-slate-100 pt-3 text-xs font-semibold">
                                <Link href={`/classes/${schoolClass.id}`} className="text-teal-800 hover:text-teal-950">View</Link>
                                {can.update && !yearLocked && <Link href={`/classes/${schoolClass.id}/edit`} className="text-teal-800 hover:text-teal-950">Edit</Link>}
                                {can.delete && !yearLocked && <button type="button" onClick={() => deleteClass(schoolClass)} className="text-rose-700 hover:text-rose-900">Delete</button>}
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </AppLayout>
    );
}

function Stat({ label, value }) {
    return (
        <div className="rounded-xl bg-slate-50 px-2 py-2.5">
            <dd className="text-base font-semibold text-slate-900">{value}</dd>
            <dt className="text-xs text-slate-500">{label}</dt>
        </div>
    );
}