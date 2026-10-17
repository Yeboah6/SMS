import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import { Field, inputClass } from '../Auth/Login';

export default function PromotionsIndex({
    sourceYears,
    targetYears,
    selectedSourceYearId,
    selectedTargetYearId,
    classes,
    students,
    recentPromotions,
    canOverride,
}) {
    const { status: flashStatus } = usePage().props;
    const [sourceYearId, setSourceYearId] = useState(String(selectedSourceYearId || ''));
    const [targetYearId, setTargetYearId] = useState(String(selectedTargetYearId || ''));
    const form = useForm({
        source_academic_year_id: selectedSourceYearId || '',
        target_academic_year_id: selectedTargetYearId || '',
        class_id: '',
        section_id: '',
        decisions: [],
    });
    const selectedClass = classes.find((schoolClass) => String(schoolClass.id) === String(form.data.class_id));

    function loadStudents(event) {
        event.preventDefault();
        form.clearErrors();
        form.setData({
            source_academic_year_id: sourceYearId,
            target_academic_year_id: targetYearId,
            class_id: '',
            section_id: '',
            decisions: [],
        });
        router.get('/promotions', {
            source_year_id: sourceYearId,
            target_year_id: targetYearId,
        }, { preserveState: true, replace: true });
    }

    function changePage(page) {
        router.get('/promotions', {
            source_year_id: selectedSourceYearId,
            target_year_id: selectedTargetYearId,
            page,
        }, { preserveState: true, preserveScroll: true });
    }

    function toggleStudent(studentId, checked) {
        const current = form.data.decisions.filter((decision) => decision.student_id !== studentId);
        if (checked) {
            current.push({ student_id: studentId, outcome: 'promoted', reason: '' });
        }
        form.setData('decisions', current);
    }

    function updateDecision(studentId, key, value) {
        form.setData('decisions', form.data.decisions.map((decision) => {
            if (decision.student_id !== studentId) {
                return decision;
            }

            return {
                ...decision,
                [key]: value,
                ...(key === 'outcome' && value === 'promoted' ? { reason: '' } : {}),
            };
        }));
    }

    function submitPromotions(event) {
        event.preventDefault();
        form.post('/promotions', {
            preserveScroll: true,
            onSuccess: () => form.reset('decisions'),
        });
    }

    const pageError = form.errors.decisions;

    return (
        <AppLayout title="Student promotions" description="Review completed-year enrolments and record the next academic placement.">
            <Head title="Student promotions" />
            {flashStatus && <div role="status" className="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{flashStatus}</div>}

            <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <div className="mb-5">
                    <h2 className="font-semibold text-slate-900">Promotion setup</h2>
                    <p className="mt-1 text-sm text-slate-500">Choose a completed source year and a later draft or active year to load eligible students.</p>
                </div>
                <form onSubmit={loadStudents} className="grid gap-4 sm:grid-cols-3">
                    <Field label="Source academic year">
                        <select value={sourceYearId} onChange={(event) => setSourceYearId(event.target.value)} className={inputClass(false)} required>
                            <option value="">Select completed year</option>
                            {sourceYears.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                        </select>
                    </Field>
                    <Field label="Target academic year">
                        <select value={targetYearId} onChange={(event) => setTargetYearId(event.target.value)} className={inputClass(false)} required>
                            <option value="">Select later year</option>
                            {targetYears.map((year) => <option key={year.id} value={year.id}>{year.name} · {year.status}</option>)}
                        </select>
                    </Field>
                    <div className="flex items-end">
                        <button className="w-full rounded-xl border border-teal-800 px-4 py-2.5 text-sm font-semibold text-teal-800 hover:bg-teal-50">Load eligible students</button>
                    </div>
                </form>
            </section>

            {selectedSourceYearId && selectedTargetYearId && (
                <form onSubmit={submitPromotions}>
                    <section className="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white">
                        <div className="flex flex-col gap-4 border-b border-slate-100 px-5 py-4 lg:flex-row lg:items-end lg:justify-between">
                            <div>
                                <h2 className="font-semibold text-slate-900">Eligible students</h2>
                                <p className="mt-1 text-sm text-slate-500">{students.total.toLocaleString()} active students have an active source-year enrolment and no target-year enrolment.</p>
                            </div>
                            <div className="grid gap-3 sm:grid-cols-2">
                                <Field label="Target class" error={form.errors.class_id}>
                                    <select value={form.data.class_id} onChange={(event) => form.setData({ ...form.data, class_id: event.target.value, section_id: '' })} className={inputClass(form.errors.class_id)} required>
                                        <option value="">Select class</option>
                                        {classes.map((schoolClass) => <option key={schoolClass.id} value={schoolClass.id}>{schoolClass.name}</option>)}
                                    </select>
                                </Field>
                                <Field label="Target section" error={form.errors.section_id}>
                                    <select value={form.data.section_id} onChange={(event) => form.setData('section_id', event.target.value)} className={inputClass(form.errors.section_id)} disabled={!selectedClass} required>
                                        <option value="">Select section</option>
                                        {selectedClass?.sections.map((section) => <option key={section.id} value={section.id}>{section.name}</option>)}
                                    </select>
                                </Field>
                            </div>
                        </div>

                        {pageError && <p role="alert" className="mx-5 mt-4 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700">{pageError}</p>}

                        {students.data.length === 0 ? (
                            <div className="px-6 py-14 text-center">
                                <p className="font-medium text-slate-800">No eligible students found</p>
                                <p className="mt-1 text-sm text-slate-500">Check that students have active source-year enrolments and are not already enrolled in the target year.</p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[850px] text-left text-sm">
                                    <thead className="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        <tr>
                                            <th className="px-5 py-3">Select</th>
                                            <th className="px-5 py-3">Student</th>
                                            <th className="px-5 py-3">Current placement</th>
                                            <th className="px-5 py-3">Decision</th>
                                            {canOverride && <th className="px-5 py-3">Override reason</th>}
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {students.data.map((student) => {
                                            const decisionIndex = form.data.decisions.findIndex((decision) => decision.student_id === student.id);
                                            const decision = form.data.decisions[decisionIndex];
                                            const reasonError = form.errors[`decisions.${decisionIndex}.reason`];

                                            return (
                                                <tr key={student.id} className="align-top hover:bg-slate-50/70">
                                                    <td className="px-5 py-4">
                                                        <input
                                                            type="checkbox"
                                                            aria-label={`Select ${student.name}`}
                                                            checked={Boolean(decision)}
                                                            disabled={!decision && form.data.decisions.length >= 100}
                                                            onChange={(event) => toggleStudent(student.id, event.target.checked)}
                                                            className="size-4 rounded border-slate-300 text-teal-800 focus:ring-teal-700"
                                                        />
                                                    </td>
                                                    <td className="px-5 py-4">
                                                        <p className="font-medium text-slate-900">{student.name}</p>
                                                        <p className="mt-0.5 text-xs text-slate-500">{student.admissionNumber}</p>
                                                    </td>
                                                    <td className="px-5 py-4 text-slate-600">{student.class} · Section {student.section}</td>
                                                    <td className="px-5 py-4">
                                                        {decision && canOverride ? (
                                                            <select value={decision.outcome} onChange={(event) => updateDecision(student.id, 'outcome', event.target.value)} className="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                                                <option value="promoted">Promote</option>
                                                                <option value="repeated">Repeat year</option>
                                                                <option value="graduated">Graduate</option>
                                                            </select>
                                                        ) : (
                                                            <span className="text-slate-600">{decision ? 'Promote' : '—'}</span>
                                                        )}
                                                        {decision && form.errors[`decisions.${decisionIndex}.outcome`] && <p role="alert" className="mt-1 text-xs text-rose-700">{form.errors[`decisions.${decisionIndex}.outcome`]}</p>}
                                                    </td>
                                                    {canOverride && (
                                                        <td className="px-5 py-4">
                                                            {decision && decision.outcome !== 'promoted' ? (
                                                                <>
                                                                    <textarea
                                                                        rows={2}
                                                                        aria-label={`Override reason for ${student.name}`}
                                                                        value={decision.reason}
                                                                        onChange={(event) => updateDecision(student.id, 'reason', event.target.value)}
                                                                        className={`w-full min-w-56 rounded-lg border px-3 py-2 text-sm ${reasonError ? 'border-rose-400' : 'border-slate-200'}`}
                                                                        required
                                                                    />
                                                                    {reasonError && <p role="alert" className="mt-1 text-xs text-rose-700">{reasonError}</p>}
                                                                </>
                                                            ) : <span className="text-slate-400">Not required</span>}
                                                        </td>
                                                    )}
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}

                        {students.last_page > 1 && (
                            <div className="flex items-center justify-between border-t border-slate-100 px-5 py-3">
                                <button type="button" disabled={students.current_page <= 1} onClick={() => changePage(students.current_page - 1)} className="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 disabled:opacity-40">Previous</button>
                                <span className="text-xs text-slate-500">Page {students.current_page} of {students.last_page} · selections are kept while paging</span>
                                <button type="button" disabled={students.current_page >= students.last_page} onClick={() => changePage(students.current_page + 1)} className="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 disabled:opacity-40">Next</button>
                            </div>
                        )}

                        <div className="flex flex-col gap-3 border-t border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <p className="text-sm text-slate-600">{form.data.decisions.length} selected · up to 100 students per batch</p>
                            <button disabled={form.processing || form.data.decisions.length === 0} className="rounded-xl bg-teal-800 px-4 py-3 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-50">
                                {form.processing ? 'Recording decisions…' : 'Record promotion decisions'}
                            </button>
                        </div>
                    </section>
                </form>
            )}

            <section className="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div className="border-b border-slate-100 px-5 py-4">
                    <h2 className="font-semibold text-slate-900">Recent promotion decisions</h2>
                </div>
                {recentPromotions.length === 0 ? (
                    <p className="px-5 py-8 text-sm text-slate-500">No promotion decisions have been recorded.</p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[760px] text-left text-sm">
                            <thead className="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th className="px-5 py-3">Student</th>
                                    <th className="px-5 py-3">Academic years</th>
                                    <th className="px-5 py-3">Outcome</th>
                                    <th className="px-5 py-3">Reason</th>
                                    <th className="px-5 py-3">Recorded by</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {recentPromotions.map((promotion, index) => (
                                    <tr key={`${promotion.admissionNumber}-${index}`}>
                                        <td className="px-5 py-4">
                                            <p className="font-medium text-slate-900">{promotion.student}</p>
                                            <p className="text-xs text-slate-500">{promotion.admissionNumber}</p>
                                        </td>
                                        <td className="px-5 py-4 text-slate-600">{promotion.sourceYear} → {promotion.targetYear}</td>
                                        <td className="px-5 py-4 capitalize text-slate-700">{promotion.outcome}</td>
                                        <td className="max-w-sm px-5 py-4 text-slate-600">{promotion.reason || '—'}</td>
                                        <td className="px-5 py-4 text-slate-600">{promotion.processedBy}<span className="block text-xs text-slate-500">{promotion.processedAt}</span></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>
        </AppLayout>
    );
}
