import { useForm } from '@inertiajs/react';
import { inputClass } from '../../Pages/Auth/Login';

export default function MarkEntryForm({ exam, subject, sectionId, roster, canRecordMarks }) {
    const maxMarks = subject.maxMarks;
    const form = useForm({
        section_id: sectionId || '',
        records: roster.map((student) => ({
            student_id: student.studentId,
            status: student.status,
            marks_obtained: student.marksObtained ?? '',
            remarks: student.remarks || '',
        })),
    });

    function updateRecord(studentId, key, value) {
        form.setData('records', form.data.records.map((record) => (record.student_id === studentId ? { ...record, [key]: value } : record)));
    }

    function save(event) {
        event.preventDefault();
        form.post(`/exams/${exam.id}/subjects/${subject.id}/marks`, { preserveScroll: true });
    }

    if (roster.length === 0) {
        return <p className="rounded-xl bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">No students are enrolled in this section for the selected exam date.</p>;
    }

    return (
        <form onSubmit={save} className="rounded-2xl border border-slate-200 bg-white">
            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-4">
                <div>
                    <h3 className="font-semibold text-slate-900">{subject.name} · {roster.length} students</h3>
                    <p className="mt-1 text-sm text-slate-500">Maximum {maxMarks} points</p>
                </div>
                {canRecordMarks && <button type="submit" disabled={form.processing} className="rounded-lg bg-teal-700 px-3 py-2 text-sm font-semibold text-white hover:bg-teal-800 disabled:opacity-50">{form.processing ? 'Saving…' : 'Save marks'}</button>}
            </div>
            {form.errors.records && <p role="alert" className="mx-5 mt-4 rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700">{form.errors.records}</p>}
            <div className="overflow-x-auto">
                <table className="w-full min-w-[720px] text-left text-sm">
                    <thead className="bg-slate-50 text-xs text-slate-500">
                        <tr>
                            <th className="px-5 py-3">Student</th>
                            <th className="px-5 py-3">Status</th>
                            <th className="px-5 py-3">Marks / {maxMarks}</th>
                            <th className="px-5 py-3">Remarks</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {roster.map((student, index) => {
                            const record = form.data.records[index];
                            return (
                                <tr key={student.studentId}>
                                    <td className="px-5 py-3">
                                        <p className="font-medium text-slate-800">{student.name}</p>
                                        <p className="text-xs text-slate-500">{student.admissionNumber}</p>
                                    </td>
                                    <td className="px-5 py-3">
                                        <select value={record.status} onChange={(event) => updateRecord(student.studentId, 'status', event.target.value)} disabled={!canRecordMarks} className={`${inputClass(false)} min-w-32`}>
                                            <option value="present">Present</option>
                                            <option value="absent">Absent</option>
                                            <option value="exempt">Exempt</option>
                                        </select>
                                    </td>
                                    <td className="px-5 py-3">
                                        <input type="number" min="0" max={maxMarks} step="0.01" value={record.marks_obtained} onChange={(event) => updateRecord(student.studentId, 'marks_obtained', event.target.value)} disabled={!canRecordMarks || record.status !== 'present'} className={`${inputClass(form.errors[`records.${index}.marks_obtained`])} w-28`} />
                                    </td>
                                    <td className="px-5 py-3">
                                        <input type="text" value={record.remarks} onChange={(event) => updateRecord(student.studentId, 'remarks', event.target.value)} disabled={!canRecordMarks} maxLength={1000} className={`${inputClass(false)} min-w-48`} />
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
        </form>
    );
}