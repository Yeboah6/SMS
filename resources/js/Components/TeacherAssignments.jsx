import { useForm } from '@inertiajs/react';

export default function TeacherAssignments({ section, subjects, teachers }) {
    const classTeacherForm = useForm({ staff_id: '' });
    const subjectTeacherForm = useForm({ staff_id: '', subject_id: '' });

    function assignClassTeacher(event) {
        event.preventDefault();
        classTeacherForm.post(`/sections/${section.id}/teacher-assignments`, {
            preserveScroll: true,
            onSuccess: () => classTeacherForm.reset(),
        });
    }

    function assignSubjectTeacher(event) {
        event.preventDefault();
        subjectTeacherForm.post(`/sections/${section.id}/subject-assignments`, {
            preserveScroll: true,
            onSuccess: () => subjectTeacherForm.reset(),
        });
    }

    return (
        <div className="grid gap-6 lg:grid-cols-2">
            <div>
                <h3 className="text-sm font-semibold text-slate-800">Class teachers</h3>
                <p className="mt-1 text-xs text-slate-500">
                    {section.teacherAssignments.length
                        ? section.teacherAssignments.map((assignment) => assignment.name).join(', ')
                        : 'No teachers assigned'}
                </p>
                <form onSubmit={assignClassTeacher} className="mt-2 flex gap-2">
                    <select value={classTeacherForm.data.staff_id} onChange={(event) => classTeacherForm.setData('staff_id', event.target.value)} className="min-w-0 flex-1 rounded-lg border border-slate-200 px-2 py-2 text-xs" required>
                        <option value="">Select teacher</option>
                        {teachers.map((teacher) => <option key={teacher.id} value={teacher.id}>{teacher.name} · {teacher.staffNumber}</option>)}
                    </select>
                    <button disabled={classTeacherForm.processing || teachers.length === 0} className="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 disabled:opacity-50">Assign</button>
                </form>
                {classTeacherForm.errors.staff_id && <p role="alert" className="mt-1 text-xs text-rose-700">{classTeacherForm.errors.staff_id}</p>}
            </div>

            {subjects.length > 0 && (
                <div>
                    <h3 className="text-sm font-semibold text-slate-800">Subject teachers</h3>
                    <p className="mt-1 text-xs text-slate-500">
                        {section.subjectAssignments.length
                            ? section.subjectAssignments.map((assignment) => `${assignment.subjectName}: ${assignment.staffName}`).join(' · ')
                            : 'No subject teachers assigned'}
                    </p>
                    <form onSubmit={assignSubjectTeacher} className="mt-2 flex flex-wrap gap-2">
                        <select value={subjectTeacherForm.data.staff_id} onChange={(event) => subjectTeacherForm.setData('staff_id', event.target.value)} className="min-w-0 flex-1 rounded-lg border border-slate-200 px-2 py-2 text-xs" required>
                            <option value="">Select teacher</option>
                            {teachers.map((teacher) => <option key={teacher.id} value={teacher.id}>{teacher.name}</option>)}
                        </select>
                        <select value={subjectTeacherForm.data.subject_id} onChange={(event) => subjectTeacherForm.setData('subject_id', event.target.value)} className="min-w-0 flex-1 rounded-lg border border-slate-200 px-2 py-2 text-xs" required>
                            <option value="">Select subject</option>
                            {subjects.map((subject) => <option key={subject.id} value={subject.id}>{subject.name}</option>)}
                        </select>
                        <button disabled={subjectTeacherForm.processing || teachers.length === 0} className="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 disabled:opacity-50">Assign</button>
                    </form>
                    {(subjectTeacherForm.errors.staff_id || subjectTeacherForm.errors.subject_id) && <p role="alert" className="mt-1 text-xs text-rose-700">{subjectTeacherForm.errors.staff_id || subjectTeacherForm.errors.subject_id}</p>}
                </div>
            )}
        </div>
    );
}