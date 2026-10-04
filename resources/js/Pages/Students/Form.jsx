import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { Field, inputClass } from '../Auth/Login';

const newGuardian = () => ({
    name: '',
    phone: '',
    email: '',
    occupation: '',
    address: '',
    relationship: 'Parent',
    is_primary: false,
    lives_with_student: false,
});

export default function StudentForm({ student }) {
    const form = useForm({
        admission_number: student?.admissionNumber || '',
        first_name: student?.firstName || '',
        middle_name: student?.middleName || '',
        last_name: student?.lastName || '',
        date_of_birth: student?.dateOfBirth || '',
        gender: student?.gender || '',
        blood_group: student?.bloodGroup || '',
        religion: student?.religion || '',
        address: student?.address || '',
        photo: null,
        admission_date: student?.admissionDate || '',
        status: student?.status || 'active',
        guardians: student?.guardians?.length ? student.guardians : [{ ...newGuardian(), is_primary: true }],
    });

    function submit(event) {
        event.preventDefault();

        if (student) {
            form.transform((data) => ({ ...data, _method: 'put' }))
                .post(`/students/${student.id}`, { forceFormData: true });
        } else {
            form.post('/students', { forceFormData: true });
        }
    }

    function updateGuardian(index, key, value) {
        form.setData('guardians', form.data.guardians.map((guardian, guardianIndex) => (
            guardianIndex === index ? { ...guardian, [key]: value } : guardian
        )));
    }

    function setPrimary(index) {
        form.setData('guardians', form.data.guardians.map((guardian, guardianIndex) => ({
            ...guardian,
            is_primary: guardianIndex === index,
        })));
    }

    return (
        <AppLayout
            title={student ? 'Edit student' : 'Add student'}
            description="Maintain permanent student identity and guardian records."
        >
            <Head title={student ? 'Edit student' : 'Add student'} />
            <form onSubmit={submit} className="space-y-6">
                <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <div className="mb-5">
                        <h2 className="font-semibold text-slate-900">Student profile</h2>
                        <p className="mt-1 text-sm text-slate-500">Academic class and section placements are recorded separately by year.</p>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        <Field label="Admission number" error={form.errors.admission_number}>
                            <input value={form.data.admission_number} onChange={(event) => form.setData('admission_number', event.target.value)} className={inputClass(form.errors.admission_number)} required />
                        </Field>
                        <Field label="First name" error={form.errors.first_name}>
                            <input value={form.data.first_name} onChange={(event) => form.setData('first_name', event.target.value)} className={inputClass(form.errors.first_name)} required />
                        </Field>
                        <Field label="Middle name" error={form.errors.middle_name}>
                            <input value={form.data.middle_name} onChange={(event) => form.setData('middle_name', event.target.value)} className={inputClass(form.errors.middle_name)} />
                        </Field>
                        <Field label="Last name" error={form.errors.last_name}>
                            <input value={form.data.last_name} onChange={(event) => form.setData('last_name', event.target.value)} className={inputClass(form.errors.last_name)} required />
                        </Field>
                        <Field label="Date of birth" error={form.errors.date_of_birth}>
                            <input type="date" value={form.data.date_of_birth} onChange={(event) => form.setData('date_of_birth', event.target.value)} className={inputClass(form.errors.date_of_birth)} required />
                        </Field>
                        <Field label="Gender" error={form.errors.gender}>
                            <select value={form.data.gender} onChange={(event) => form.setData('gender', event.target.value)} className={inputClass(form.errors.gender)} required>
                                <option value="">Select gender</option>
                                <option value="female">Female</option>
                                <option value="male">Male</option>
                                <option value="other">Other</option>
                                <option value="prefer_not_to_say">Prefer not to say</option>
                            </select>
                        </Field>
                        <Field label="Blood group" error={form.errors.blood_group}>
                            <select value={form.data.blood_group} onChange={(event) => form.setData('blood_group', event.target.value)} className={inputClass(form.errors.blood_group)}>
                                <option value="">Not recorded</option>
                                {['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'].map((group) => <option key={group} value={group}>{group}</option>)}
                            </select>
                        </Field>
                        <Field label="Religion" error={form.errors.religion}>
                            <input value={form.data.religion} onChange={(event) => form.setData('religion', event.target.value)} className={inputClass(form.errors.religion)} />
                        </Field>
                        <Field label="Admission date" error={form.errors.admission_date}>
                            <input type="date" value={form.data.admission_date} onChange={(event) => form.setData('admission_date', event.target.value)} className={inputClass(form.errors.admission_date)} required />
                        </Field>
                        <Field label="Status" error={form.errors.status}>
                            <select value={form.data.status} onChange={(event) => form.setData('status', event.target.value)} className={inputClass(form.errors.status)} required>
                                {['active', 'inactive', 'graduated', 'transferred', 'left'].map((status) => <option key={status} value={status}>{status.replace('_', ' ')}</option>)}
                            </select>
                        </Field>
                        <Field label="Student photo (JPEG, PNG, or WebP; up to 5 MB)" error={form.errors.photo}>
                            <input type="file" accept=".jpg,.jpeg,.png,.webp" onChange={(event) => form.setData('photo', event.target.files?.[0] || null)} className={inputClass(form.errors.photo)} />
                        </Field>
                        <div className="sm:col-span-2 xl:col-span-3">
                            <Field label="Address" error={form.errors.address}>
                                <textarea rows={3} value={form.data.address} onChange={(event) => form.setData('address', event.target.value)} className={inputClass(form.errors.address)} />
                            </Field>
                        </div>
                    </div>
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 className="font-semibold text-slate-900">Guardians</h2>
                            <p className="mt-1 text-sm text-slate-500">Add at least one contact and choose exactly one primary guardian.</p>
                        </div>
                        <button type="button" onClick={() => form.setData('guardians', [...form.data.guardians, newGuardian()])} className="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Add guardian</button>
                    </div>
                    {form.errors.guardians && <p role="alert" className="mt-3 text-sm text-rose-700">{form.errors.guardians}</p>}
                    <div className="mt-4 space-y-4">
                        {form.data.guardians.map((guardian, index) => (
                            <div key={guardian.guardian_id || index} className="rounded-xl border border-slate-200 p-4">
                                <div className="mb-4 flex items-center justify-between gap-3">
                                    <p className="text-sm font-semibold text-slate-800">Guardian {index + 1}</p>
                                    <div className="flex items-center gap-3">
                                        <label className="flex items-center gap-2 text-xs font-medium text-slate-600">
                                            <input type="radio" checked={guardian.is_primary} onChange={() => setPrimary(index)} className="size-4 border-slate-300 text-teal-700 focus:ring-teal-700" />
                                            Primary
                                        </label>
                                        {form.data.guardians.length > 1 && (
                                            <button type="button" onClick={() => form.setData('guardians', form.data.guardians.filter((_, guardianIndex) => guardianIndex !== index))} className="text-xs font-semibold text-rose-700 hover:text-rose-900">Remove</button>
                                        )}
                                    </div>
                                </div>
                                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                    <Field label="Full name" error={form.errors[`guardians.${index}.name`]}>
                                        <input value={guardian.name || ''} onChange={(event) => updateGuardian(index, 'name', event.target.value)} className={inputClass(form.errors[`guardians.${index}.name`])} required={!guardian.guardian_id} />
                                    </Field>
                                    <Field label="Relationship" error={form.errors[`guardians.${index}.relationship`]}>
                                        <input value={guardian.relationship || ''} onChange={(event) => updateGuardian(index, 'relationship', event.target.value)} className={inputClass(form.errors[`guardians.${index}.relationship`])} placeholder="Mother, father, or guardian" required />
                                    </Field>
                                    <Field label="Phone" error={form.errors[`guardians.${index}.phone`]}>
                                        <input type="tel" value={guardian.phone || ''} onChange={(event) => updateGuardian(index, 'phone', event.target.value)} className={inputClass(form.errors[`guardians.${index}.phone`])} required={!guardian.guardian_id} />
                                    </Field>
                                    <Field label="Email" error={form.errors[`guardians.${index}.email`]}>
                                        <input type="email" value={guardian.email || ''} onChange={(event) => updateGuardian(index, 'email', event.target.value)} className={inputClass(form.errors[`guardians.${index}.email`])} />
                                    </Field>
                                    <Field label="Occupation" error={form.errors[`guardians.${index}.occupation`]}>
                                        <input value={guardian.occupation || ''} onChange={(event) => updateGuardian(index, 'occupation', event.target.value)} className={inputClass(form.errors[`guardians.${index}.occupation`])} />
                                    </Field>
                                    <Field label="Address" error={form.errors[`guardians.${index}.address`]}>
                                        <input value={guardian.address || ''} onChange={(event) => updateGuardian(index, 'address', event.target.value)} className={inputClass(form.errors[`guardians.${index}.address`])} />
                                    </Field>
                                    <label className="flex items-center gap-2 self-end pb-3 text-sm text-slate-700">
                                        <input type="checkbox" checked={Boolean(guardian.lives_with_student)} onChange={(event) => updateGuardian(index, 'lives_with_student', event.target.checked)} className="size-4 rounded border-slate-300 text-teal-700 focus:ring-teal-700" />
                                        Lives with student
                                    </label>
                                </div>
                            </div>
                        ))}
                    </div>
                </section>

                <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <Link href={student ? `/students/${student.id}` : '/students'} className="rounded-xl border border-slate-200 bg-white px-4 py-3 text-center text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</Link>
                    <button disabled={form.processing} className="rounded-xl bg-teal-800 px-5 py-3 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-60">{student ? 'Save changes' : 'Create student'}</button>
                </div>
            </form>
        </AppLayout>
    );
}
