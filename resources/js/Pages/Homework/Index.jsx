import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import PageAlerts from '../../Components/Exams/PageAlerts';
import Pill from '../../Components/Homework/Pill';
import { Field, inputClass } from '../Auth/Login';
import { MY_STATUS, WINDOW, dateLabel } from '../../lib/homework';

export default function HomeworkIndex({ academicYear, assignments, filters, role, children, selectedStudentId, canCreate }) {
    const [search, setSearch] = useState(filters.search || '');
    const isStaff = role === 'staff';
    const tabs = [['', 'All'], ['open', 'Open'], ['closed', 'Closed'], ...(isStaff ? [['grading', 'Needs grading']] : [])];

    function visit(overrides = {}) {
        const params = { search, view: filters.view, student_id: selectedStudentId, ...overrides };
        Object.keys(params).forEach((key) => !params[key] && delete params[key]);
        router.get('/homework', params, { preserveState: true, replace: true });
    }

    return (
        <AppLayout title="Homework" description={academicYear ? `Assignments for ${academicYear.name}.` : 'Assignments and student submissions.'}>
            <Head title="Homework" />
            <PageAlerts />

            <nav aria-label="Homework view" className="mb-5 flex gap-2 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-2">
                {tabs.map(([value, label]) => <button key={value || 'all'} type="button" aria-pressed={filters.view === value} onClick={() => visit({ view: value })} className={`shrink-0 rounded-xl px-4 py-2.5 text-sm font-semibold ${filters.view === value ? 'bg-teal-800 text-white' : 'text-slate-600 hover:bg-slate-100'}`}>{label}</button>)}
            </nav>

            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div className="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <h2 className="font-semibold text-slate-900">{role === 'student' ? 'My assignments' : role === 'parent' ? 'Children’s assignments' : 'Assignments'} <span className="text-sm font-normal text-slate-500">· {assignments.total.toLocaleString()}</span></h2>
                    <div className="flex flex-wrap items-center gap-3">
                        {role === 'parent' && (
                            <select aria-label="Child" value={selectedStudentId || ''} onChange={(event) => visit({ student_id: event.target.value })} className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                                <option value="">All linked children</option>
                                {children.map((child) => <option key={child.id} value={child.id}>{child.name}</option>)}
                            </select>
                        )}
                        <form onSubmit={(event) => { event.preventDefault(); visit(); }} className="flex gap-2">
                            <input type="search" aria-label="Search homework" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search by title" className="w-48 rounded-xl border border-slate-200 px-3 py-2 text-sm" />
                            <button className="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Search</button>
                        </form>
                        {canCreate && <Link href="/homework/create" className="rounded-xl bg-teal-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-900">Assign homework</Link>}
                    </div>
                </div>

                {assignments.data.length === 0 ? (
                    <div className="px-6 py-14 text-center"><p className="font-medium text-slate-800">No homework found</p><p className="mt-1 text-sm text-slate-500">Assignments appear here for the active academic year and your sections.</p></div>
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {assignments.data.map((assignment) => (
                            <li key={assignment.id} className="px-5 py-4">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Link href={`/homework/${assignment.id}`} className="font-semibold text-slate-900 hover:text-teal-800">{assignment.title}</Link>
                                            <Pill map={WINDOW} value={assignment.window} />
                                            {role === 'student' && <Pill map={MY_STATUS} value={assignment.myStatus} />}
                                        </div>
                                        <p className="mt-1 text-sm text-slate-600">{assignment.subject} · {assignment.section}</p>
                                        <p className="mt-2 line-clamp-2 text-sm text-slate-500">{assignment.excerpt}</p>
                                    </div>
                                    <div className="text-right text-xs text-slate-500">
                                        <p className="font-semibold text-slate-800">Due {dateLabel(assignment.dueDate)}</p>
                                        <p className="mt-0.5">{assignment.maxPoints} points</p>
                                        {role === 'student' && assignment.myStatus === 'graded' && <p className="mt-1 font-semibold text-emerald-700">{assignment.myScore} / {assignment.maxPoints}</p>}
                                        {isStaff && <p className="mt-1">{assignment.submitted} of {assignment.roster} submitted{assignment.ungraded > 0 && <span className="block font-semibold text-amber-700">{assignment.ungraded} to grade</span>}</p>}
                                        {role === 'parent' && <p className="mt-1">{assignment.submitted} submission{assignment.submitted === 1 ? '' : 's'}</p>}
                                    </div>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}

                {assignments.links.length > 3 && (
                    <nav aria-label="Homework pages" className="flex flex-wrap justify-center gap-1 border-t border-slate-100 px-4 py-4">
                        {assignments.links.map((link, index) => (
                            <Link key={`${link.label}-${index}`} href={link.url || '#'} preserveScroll preserveState aria-current={link.active ? 'page' : undefined} className={`rounded-lg px-3 py-2 text-sm ${link.active ? 'bg-teal-800 font-semibold text-white' : 'text-slate-600 hover:bg-slate-100'} ${!link.url ? 'pointer-events-none opacity-40' : ''}`}>
                                {link.label.replace(/<[^>]*>/g, '').replaceAll('&laquo;', '‹').replaceAll('&raquo;', '›')}
                            </Link>
                        ))}
                    </nav>
                )}
            </section>
        </AppLayout>
    );
}