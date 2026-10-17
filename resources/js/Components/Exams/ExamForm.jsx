import { Link } from '@inertiajs/react';
import { Field, inputClass } from '../../Pages/Auth/Login';
import { EXAM_TYPES, humanize } from '../../lib/exams';

/**
 * Shared by Exams/Create and Exams/Edit.
 * - Create: pass `classes` and `onClassChange` so a class can be chosen.
 * - Edit: omit them; the class is shown read-only. `lockedSubjectIds` cannot be unticked (marks exist).
 */
export default function ExamForm({
    form,
    onSubmit,
    terms,
    classes = null,
    selectedClass,
    onClassChange = null,
    lockedSubjectIds = [],
    cancelHref,
    submitLabel,
}) {
    function toggleSubject(subjectId, checked) {
        const current = form.data.subjects.filter((item) => item.subject_id !== subjectId);
        if (checked) {
            current.push({ subject_id: subjectId, exam_date: form.data.start_date, max_marks: 100, pass_marks: 40 });
        }
        form.setData('subjects', current);
    }

    function updateSubject(subjectId, key, value) {
        form.setData('subjects', form.data.subjects.map((item) => (item.subject_id === subjectId ? { ...item, [key]: value } : item)));
    }

    return (
        <form onSubmit={onSubmit} className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            {onClassChange ? (
                <Field label="Class" error={form.errors.class_id}>
                    <select value={form.data.class_id} onChange={(event) => onClassChange(event.target.value)} className={inputClass(form.errors.class_id)} required>
                        <option value="">Select class</option>
                        {classes.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
                    </select>
                </Field>
            ) : (
                <div>
                    <p className="text-sm font-medium text-slate-700">Class</p>
                    <p className="mt-2 text-sm text-slate-900">{selectedClass?.name}</p>
                </div>
            )}
            <Field label="Term (optional)" error={form.errors.term_id}>
                <select value={form.data.term_id} onChange={(event) => form.setData('term_id', event.target.value)} className={inputClass(form.errors.term_id)}>
                    <option value="">No term</option>
                    {terms.map((term) => <option key={term.id} value={term.id}>{term.name}</option>)}
                </select>
            </Field>
            <Field label="Exam name" error={form.errors.name}>
                <input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} className={inputClass(form.errors.name)} maxLength={150} required />
            </Field>
            <Field label="Exam type" error={form.errors.exam_type}>
                <select value={form.data.exam_type} onChange={(event) => form.setData('exam_type', event.target.value)} className={inputClass(form.errors.exam_type)}>
                    {EXAM_TYPES.map((type) => <option key={type} value={type}>{humanize(type)}</option>)}
                </select>
            </Field>
            <Field label="Start date" error={form.errors.start_date}>
                <input type="date" value={form.data.start_date} onChange={(event) => form.setData('start_date', event.target.value)} className={inputClass(form.errors.start_date)} required />
            </Field>
            <Field label="End date" error={form.errors.end_date}>
                <input type="date" value={form.data.end_date} onChange={(event) => form.setData('end_date', event.target.value)} className={inputClass(form.errors.end_date)} required />
            </Field>

            {selectedClass && (
                <div className="grid gap-3 sm:col-span-2 xl:col-span-3">
                    <p className="text-sm font-semibold text-slate-800">Subjects and scoring</p>
                    {(form.errors.subjects || form.errors.exam) && <p role="alert" className="text-sm text-rose-700">{form.errors.subjects || form.errors.exam}</p>}
                    {selectedClass.subjects.length === 0 ? (
                        <p className="rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900">Assign subjects to this class before creating an examination.</p>
                    ) : selectedClass.subjects.map((subject) => {
                        const index = form.data.subjects.findIndex((item) => item.subject_id === subject.id);
                        const selection = index >= 0 ? form.data.subjects[index] : null;
                        const locked = lockedSubjectIds.includes(subject.id);

                        return (
                            <div key={subject.id} className="grid gap-3 rounded-xl border border-slate-200 p-4 sm:grid-cols-[minmax(10rem,1fr)_repeat(3,minmax(8rem,0.7fr))] sm:items-end">
                                <label className="flex items-center gap-3 pb-2 text-sm font-medium text-slate-800">
                                    <input type="checkbox" checked={Boolean(selection)} disabled={locked && Boolean(selection)} onChange={(event) => toggleSubject(subject.id, event.target.checked)} className="size-4 rounded border-slate-300 text-teal-700 focus:ring-teal-600" />
                                    <span>{subject.name}{locked && <span className="ml-2 text-xs font-normal text-slate-500">Marks recorded</span>}</span>
                                </label>
                                {selection && (
                                    <>
                                        <Field label="Exam date" error={form.errors[`subjects.${index}.exam_date`]}>
                                            <input type="date" value={selection.exam_date} onChange={(event) => updateSubject(subject.id, 'exam_date', event.target.value)} disabled={locked} className={inputClass(false)} required />
                                        </Field>
                                        <Field label="Maximum" error={form.errors[`subjects.${index}.max_marks`]}>
                                            <input type="number" min="1" step="0.01" value={selection.max_marks} onChange={(event) => updateSubject(subject.id, 'max_marks', event.target.value)} className={inputClass(false)} required />
                                        </Field>
                                        <Field label="Pass mark" error={form.errors[`subjects.${index}.pass_marks`]}>
                                            <input type="number" min="0" step="0.01" value={selection.pass_marks} onChange={(event) => updateSubject(subject.id, 'pass_marks', event.target.value)} className={inputClass(false)} required />
                                        </Field>
                                    </>
                                )}
                            </div>
                        );
                    })}
                </div>
            )}

            <div className="flex flex-wrap gap-3 sm:col-span-2 xl:col-span-3">
                <button type="submit" disabled={form.processing || form.data.subjects.length === 0} className="rounded-xl bg-teal-800 px-5 py-3 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-50">
                    {form.processing ? 'Saving…' : submitLabel}
                </button>
                <Link href={cancelHref} className="rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</Link>
            </div>
        </form>
    );
}