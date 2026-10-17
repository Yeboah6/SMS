import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { Field, inputClass } from '../Auth/Login';

export default function StaffForm({ member = null, departments, designations, userAccounts, canManageUsers }) {
    const form = useForm({
        staff_number: member?.staffNumber || '',
        name: member?.name || '',
        email: member?.email || '',
        phone: member?.phone || '',
        joining_date: member?.joiningDate || '',
        department_id: member?.departmentId ? String(member.departmentId) : '',
        designation_id: member?.designationId ? String(member.designationId) : '',
        user_id: member?.userId ? String(member.userId) : '',
        is_teacher: member?.isTeacher || false,
        status: member?.status || 'active',
        qualifications: member?.qualifications || '',
    });
    const isEditing = member !== null;

    function submit(event) {
        event.preventDefault();
        const options = { preserveScroll: true };

        if (isEditing) {
            form.patch(`/staff/${member.id}`, options);
        } else {
            form.post('/staff', options);
        }
    }

    return (
        <AppLayout
            title={isEditing ? `Edit ${member.name}` : 'Create staff profile'}
            description={isEditing ? 'Update staff details and their linked account.' : 'Add a staff profile and optionally link a school account.'}
        >
            <Head title={isEditing ? 'Edit staff profile' : 'Create staff profile'} />
            <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
                <div className="mb-5">
                    <h2 className="text-lg font-semibold text-slate-900">{isEditing ? 'Staff details' : 'New staff profile'}</h2>
                    <p className="mt-1 text-sm text-slate-500">Create or manage sign-in accounts and their school roles in User Administration. Only compatible staff accounts can be linked here.</p>
                </div>
                <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <Field label="Staff number" error={form.errors.staff_number}>
                        <input value={form.data.staff_number} onChange={(event) => form.setData('staff_number', event.target.value)} className={inputClass(form.errors.staff_number)} required />
                    </Field>
                    <Field label="Full name" error={form.errors.name}>
                        <input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} className={inputClass(form.errors.name)} autoComplete="name" required />
                    </Field>
                    <Field label="Email" error={form.errors.email}>
                        <input type="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} className={inputClass(form.errors.email)} autoComplete="email" />
                    </Field>
                    <Field label="Phone" error={form.errors.phone}>
                        <input type="tel" value={form.data.phone} onChange={(event) => form.setData('phone', event.target.value)} className={inputClass(form.errors.phone)} autoComplete="tel" />
                    </Field>
                    <Field label="Department" error={form.errors.department_id}>
                        <select value={form.data.department_id} onChange={(event) => form.setData('department_id', event.target.value)} className={inputClass(form.errors.department_id)}>
                            <option value="">Not assigned</option>
                            {departments.map((department) => <option key={department.id} value={department.id}>{department.name}</option>)}
                        </select>
                    </Field>
                    <Field label="Designation" error={form.errors.designation_id}>
                        <select value={form.data.designation_id} onChange={(event) => form.setData('designation_id', event.target.value)} className={inputClass(form.errors.designation_id)}>
                            <option value="">Not assigned</option>
                            {designations.map((designation) => <option key={designation.id} value={designation.id}>{designation.name}</option>)}
                        </select>
                    </Field>
                    <Field label="Joining date" error={form.errors.joining_date}>
                        <input type="date" value={form.data.joining_date} onChange={(event) => form.setData('joining_date', event.target.value)} className={inputClass(form.errors.joining_date)} />
                    </Field>
                    <Field label="Linked account" error={form.errors.user_id}>
                        <select value={form.data.user_id} onChange={(event) => form.setData('user_id', event.target.value)} className={inputClass(form.errors.user_id)}>
                            <option value="">No linked account</option>
                            {userAccounts.map((account) => <option key={account.id} value={account.id}>{account.name} · {account.email} · {account.roles.join(', ') || 'No role'}</option>)}
                        </select>
                    </Field>
                    <Field label="Status" error={form.errors.status}>
                        <select value={form.data.status} onChange={(event) => form.setData('status', event.target.value)} className={inputClass(form.errors.status)}>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </Field>
                    <div className="sm:col-span-2 xl:col-span-3">
                        <Field label="Qualifications" error={form.errors.qualifications}>
                            <textarea rows={3} value={form.data.qualifications} onChange={(event) => form.setData('qualifications', event.target.value)} className={inputClass(form.errors.qualifications)} />
                        </Field>
                    </div>
                    <label className="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" checked={form.data.is_teacher} onChange={(event) => form.setData('is_teacher', event.target.checked)} className="size-4 rounded border-slate-300 text-teal-700 focus:ring-teal-700" />
                        This staff member teaches classes
                    </label>
                    {form.errors.is_teacher && <p role="alert" className="text-sm text-rose-700 sm:col-span-2 xl:col-span-3">{form.errors.is_teacher}</p>}
                    {form.errors.user_id && <p role="alert" className="text-sm text-rose-700 sm:col-span-2 xl:col-span-3">{form.errors.user_id}</p>}
                    <div className="flex flex-wrap items-end gap-3 sm:col-span-2 xl:col-span-3">
                        <button disabled={form.processing} className="rounded-xl bg-teal-800 px-4 py-3 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-60">
                            {form.processing ? 'Saving…' : isEditing ? 'Save changes' : 'Create staff profile'}
                        </button>
                        <Link href={isEditing ? `/staff/${member.id}` : '/staff'} className="rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</Link>
                        {canManageUsers && <Link href="/users" className="text-sm font-semibold text-teal-800 hover:text-teal-950">Manage user accounts and roles</Link>}
                    </div>
                </form>
            </section>
        </AppLayout>
    );
}
