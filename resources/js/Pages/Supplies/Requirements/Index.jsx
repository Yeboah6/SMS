import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../../Layouts/AppLayout';
import PageAlerts from '../../../Components/Exams/PageAlerts';
import SuppliesNav from '../../../Components/Supplies/SuppliesNav';
import Pill from '../../../Components/Supplies/Pill';
import { Field, inputClass } from '../../Auth/Login';
import { REQUIREMENT_STATUS } from '../../../lib/supplies';
import Pagination from '../../../Components/Pagination';

export default function RequirementsIndex({ academicYears, selectedAcademicYearId, classes, selectedClassId, completion, can }) {
    const [marking, setMarking] = useState(null);

    function go(params) {
        router.get('/supplies/requirements', { academic_year_id: selectedAcademicYearId, ...params }, { preserveScroll: true });
    }

    function remove(requirement) {
        if (window.confirm(`Remove ${requirement.itemName} from this class? Past sales are not affected.`)) router.delete(`/supplies/requirements/${requirement.id}`, { preserveScroll: true });
    }

    const selectedClass = classes.find((item) => item.id === selectedClassId);
    const studentPaginator = completion?.students;
    const students = studentPaginator?.data || [];
    const completeCount = students.filter((student) => student.complete).length;

    return (
        <AppLayout title="Class requirements" description="The books and supplies each class is expected to have, and how far each student has got.">
            <Head title="Class requirements" />
            <PageAlerts />
            <SuppliesNav current="requirements" />

            <section className="mb-6 flex flex-wrap items-end gap-4 rounded-2xl border border-slate-200 bg-white p-5">
                <Field label="Academic year"><select value={selectedAcademicYearId || ''} onChange={(event) => router.get('/supplies/requirements', { academic_year_id: event.target.value })} className={inputClass(false)}>{academicYears.map((year) => <option key={year.id} value={year.id}>{year.name} · {year.status}</option>)}</select></Field>
                <Field label="Class"><select value={selectedClassId || ''} onChange={(event) => go(event.target.value ? { class_id: event.target.value } : {})} className={inputClass(false)}><option value="">Select a class</option>{classes.map((item) => <option key={item.id} value={item.id}>{item.name} ({item.requirements})</option>)}</select></Field>
                {can.manage && selectedClassId && <Link href={`/supplies/requirements/create?academic_year_id=${selectedAcademicYearId}&class_id=${selectedClassId}`} className="rounded-xl bg-teal-800 px-5 py-3 text-sm font-semibold text-white hover:bg-teal-900">Add requirement</Link>}
            </section>

            {!selectedClassId ? (
                <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <h2 className="font-semibold text-slate-900">Classes</h2>
                    <ul className="mt-4 grid gap-2 sm:grid-cols-2 xl:grid-cols-3">{classes.map((item) => <li key={item.id}><button type="button" onClick={() => go({ class_id: item.id })} className="flex w-full items-center justify-between rounded-xl border border-slate-200 px-4 py-3 text-left text-sm hover:border-teal-300 hover:bg-teal-50/40"><span className="font-medium text-slate-900">{item.name}</span><span className="text-xs text-slate-500">{item.requirements} requirement{item.requirements === 1 ? '' : 's'}</span></button></li>)}</ul>
                </section>
            ) : (
                <>
                    <section className="mb-6 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                        <div className="flex flex-wrap items-center justify-between gap-3"><h2 className="font-semibold text-slate-900">{selectedClass?.name}: what is expected</h2>{students.length > 0 && <span className="text-sm text-slate-600">{completeCount} of {students.length} students on this page have all required items</span>}</div>
                        {completion.requirements.length === 0 ? <p className="mt-4 rounded-xl bg-slate-50 px-3 py-4 text-sm text-slate-500">No requirements set for this class yet.</p> : (
                            <div className="mt-4 overflow-x-auto">
                                <table className="w-full min-w-[640px] text-left text-sm">
                                    <thead className="border-b border-slate-100 text-xs text-slate-500"><tr><th className="py-3 pr-4">Item</th><th className="py-3 pr-4">Per student</th><th className="py-3 pr-4">Type</th><th className="py-3 pr-4">Term</th><th className="py-3 pr-4">Complete</th><th className="py-3 pr-4">Partial</th><th className="py-3 pr-4">Outstanding</th><th /></tr></thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {completion.requirements.map((requirement) => {
                                            const counts = completion.summary[requirement.id];
                                            return (
                                                <tr key={requirement.id}>
                                                    <td className="py-3 pr-4 font-medium text-slate-800">{requirement.itemName}<span className="block text-xs font-normal text-slate-500">{requirement.sku}</span></td>
                                                    <td className="py-3 pr-4">{requirement.quantity} {requirement.unit}</td>
                                                    <td className="py-3 pr-4">{requirement.isRequired ? 'Required' : 'Recommended'}</td>
                                                    <td className="py-3 pr-4 text-slate-600">{requirement.termName || 'Whole year'}</td>
                                                    <td className="py-3 pr-4 text-emerald-700">{counts.complete + counts.external}</td>
                                                    <td className="py-3 pr-4 text-amber-700">{counts.partial}</td>
                                                    <td className="py-3 pr-4 text-rose-700">{counts.outstanding}</td>
                                                    <td className="py-3 text-right">{can.manage && <span className="flex justify-end gap-3 text-xs font-semibold"><Link href={`/supplies/requirements/${requirement.id}/edit`} className="text-teal-800 hover:text-teal-950">Edit</Link><button type="button" onClick={() => remove(requirement)} className="text-rose-700 hover:text-rose-900">Remove</button></span>}</td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </section>

                    {completion.requirements.length > 0 && (
                        <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                            <div className="border-b border-slate-100 px-5 py-4"><h2 className="font-semibold text-slate-900">Students</h2><p className="mt-1 text-sm text-slate-500">Bought counts the school's sales for this year, net of returns.{can.external && ' Staff can mark an item as bought elsewhere.'}</p></div>
                            {students.length === 0 ? <p className="px-6 py-12 text-center text-sm text-slate-500">No active students are enrolled in this class.</p> : (
                                <div className="overflow-x-auto">
                                    <table className="w-full min-w-[760px] text-left text-sm">
                                        <thead className="bg-slate-50 text-xs text-slate-500"><tr><th className="px-5 py-3">Student</th>{completion.requirements.map((requirement) => <th key={requirement.id} className="px-4 py-3">{requirement.itemName}<span className="block font-normal">need {requirement.quantity}</span></th>)}<th className="px-5 py-3">All required</th></tr></thead>
                                        <tbody className="divide-y divide-slate-100">
                                            {students.map((student) => (
                                                <tr key={student.studentId}>
                                                    <td className="px-5 py-3"><Link href={`/supplies/students/${student.studentId}`} className="font-medium text-teal-800 hover:text-teal-950">{student.name}</Link><span className="block text-xs text-slate-500">{student.admissionNumber}</span></td>
                                                    {completion.requirements.map((requirement) => {
                                                        const line = student.lines[requirement.id];
                                                        return (
                                                            <td key={requirement.id} className="px-4 py-3 align-top">
                                                                <Pill map={REQUIREMENT_STATUS} value={line.status} />
                                                                <span className="mt-1 block text-xs text-slate-500">{line.purchased} bought</span>
                                                                {can.external && (line.status === 'external'
                                                                    ? <button type="button" onClick={() => router.delete(`/supplies/requirements/${requirement.id}/external`, { data: { student_id: student.studentId }, preserveScroll: true })} className="mt-1 text-xs font-semibold text-slate-600 hover:text-slate-900">Undo</button>
                                                                    : line.status !== 'complete' && <button type="button" onClick={() => setMarking({ requirement, student })} className="mt-1 text-xs font-semibold text-teal-800 hover:text-teal-950">Bought elsewhere</button>)}
                                                            </td>
                                                        );
                                                    })}
                                                    <td className="px-5 py-3"><span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${student.complete ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-800'}`}>{student.complete ? 'Yes' : 'No'}</span></td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                            {students.length > 0 && studentPaginator && <Pagination links={studentPaginator.links} from={studentPaginator.from} to={studentPaginator.to} total={studentPaginator.total} />}
                        </section>
                    )}
                </>
            )}

            {marking && <ExternalDialog marking={marking} onClose={() => setMarking(null)} />}
        </AppLayout>
    );
}

function ExternalDialog({ marking, onClose }) {
    const form = useForm({ student_id: marking.student.studentId, notes: '' });

    return (
        <div role="dialog" aria-modal="true" aria-label="Mark as bought elsewhere" className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-4">
            <form onSubmit={(event) => { event.preventDefault(); form.post(`/supplies/requirements/${marking.requirement.id}/external`, { preserveScroll: true, onSuccess: onClose }); }} className="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                <h2 className="font-semibold text-slate-900">Bought elsewhere</h2>
                <p className="mt-1 text-sm text-slate-600">{marking.student.name} · {marking.requirement.itemName}</p>
                <div className="mt-4"><Field label="Note (optional)" error={form.errors.notes || form.errors.requirement}><input value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} className={inputClass(form.errors.notes)} maxLength={500} placeholder="e.g. Parent showed the book" /></Field></div>
                <div className="mt-5 flex gap-3"><button disabled={form.processing} className="rounded-xl bg-teal-800 px-5 py-3 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-60">Mark as bought elsewhere</button><button type="button" onClick={onClose} className="rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button></div>
            </form>
        </div>
    );
}