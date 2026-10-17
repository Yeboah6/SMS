import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Field, inputClass } from '../../Pages/Auth/Login';

/** Shared by Staff/Create (no `member`) and Staff/Edit (pass `member`). */
export default function StaffForm({ member = null, roles, departments, designations, canManageUsers }) {
    const isEditing = member !== null;
    const [showPassword, setShowPassword] = useState(false);
    const form = useForm({
        name: member?.name || '',
        email: member?.email || '',
        role: member?.role || '',
        password: '',
        password_confirmation: '',
        phone: member?.phone || '',
        joining_date: member?.joiningDate || '',
        department_id: member?.departmentId ? String(member.departmentId) : '',
        designation_id: member?.designationId ? String(member.designationId) : '',
        status: member?.status || 'active',
        qualifications: member?.qualifications || '',
    });

    const passwordRequired = !isEditing || !member.hasAccount;

    function submit(event) {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onError: () => form.reset('password', 'password_confirmation'),
        };

        if (isEditing) {
            form.patch(`/staff/${member.id}`, options);
        } else {
            form.post('/staff', options);
        }
    }

    const set = (key) => (event) => form.setData(key, event.target.value);

    return (
        <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <div>
                <p className="text-sm font-medium text-slate-700">Staff number</p>
                <p className="mt-2 text-sm text-slate-900">{isEditing ? member.staffNumber : 'Generated automatically (for example ST001)'}</p>
            </div>
            <Field label="Full name" error={form.errors.name}>
                <input value={form.data.name} onChange={set('name')} className={inputClass(form.errors.name)} autoComplete="name" required />
            </Field>
            <Field label="Email (used to sign in)" error={form.errors.email}>
                <input type="email" value={form.data.email} onChange={set('email')} className={inputClass(form.errors.email)} autoComplete="off" required />
            </Field>

            <Field label="Role" error={form.errors.role}>
                <select value={form.data.role} onChange={set('role')} className={inputClass(form.errors.role)} required>
                    <option value="">Select a role</option>
                    {roles.map((role) => <option key={role} value={role}>{role}</option>)}
                </select>
            </Field>
            <Field label={passwordRequired ? 'Password' : 'New password (optional)'} error={form.errors.password}>
                <div className="flex gap-2">
                    <input type={showPassword ? 'text' : 'password'} value={form.data.password} onChange={set('password')} className={inputClass(form.errors.password)} autoComplete="new-password" required={passwordRequired} />
                    <button type="button" onClick={() => setShowPassword((value) => !value)} aria-pressed={showPassword} className="shrink-0 rounded-xl border border-slate-200 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">{showPassword ? 'Hide' : 'Show'}</button>
                </div>
            </Field>
            <Field label="Confirm password" error={form.errors.password_confirmation}>
                <input type={showPassword ? 'text' : 'password'} value={form.data.password_confirmation} onChange={set('password_confirmation')} className={inputClass(form.errors.password_confirmation)} autoComplete="new-password" required={passwordRequired || form.data.password !== ''} />
            </Field>
            <p className="text-xs text-slate-500 sm:col-span-2 xl:col-span-3">
                {isEditing && member.hasAccount
                    ? 'Leave the password blank to keep the current one. The Teacher role allows class and subject assignments.'
                    : 'A sign-in account is created with this email, role and password. The Teacher role allows class and subject assignments.'}
            </p>

            <Field label="Phone" error={form.errors.phone}>
                <input type="tel" value={form.data.phone} onChange={set('phone')} className={inputClass(form.errors.phone)} autoComplete="tel" />
            </Field>
            <Field label="Department" error={form.errors.department_id}>
                <select value={form.data.department_id} onChange={set('department_id')} className={inputClass(form.errors.department_id)}>
                    <option value="">Not assigned</option>
                    {departments.map((department) => <option key={department.id} value={department.id}>{department.name}</option>)}
                </select>
            </Field>
            <Field label="Designation" error={form.errors.designation_id}>
                <select value={form.data.designation_id} onChange={set('designation_id')} className={inputClass(form.errors.designation_id)}>
                    <option value="">Not assigned</option>
                    {designations.map((designation) => <option key={designation.id} value={designation.id}>{designation.name}</option>)}
                </select>
            </Field>
            <Field label="Joining date" error={form.errors.joining_date}>
                <input type="date" value={form.data.joining_date} onChange={set('joining_date')} className={inputClass(form.errors.joining_date)} />
            </Field>
            <Field label="Status" error={form.errors.status}>
                <select value={form.data.status} onChange={set('status')} className={inputClass(form.errors.status)}>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </Field>
            <div className="sm:col-span-2 xl:col-span-3">
                <Field label="Qualifications" error={form.errors.qualifications}>
                    <textarea rows={3} value={form.data.qualifications} onChange={set('qualifications')} className={inputClass(form.errors.qualifications)} />
                </Field>
            </div>

            <div className="flex flex-wrap items-center gap-3 sm:col-span-2 xl:col-span-3">
                <button disabled={form.processing} className="rounded-xl bg-teal-800 px-5 py-3 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-60">
                    {form.processing ? 'Saving…' : isEditing ? 'Save changes' : 'Create staff profile'}
                </button>
                <Link href={isEditing ? `/staff/${member.id}` : '/staff'} className="rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</Link>
                {canManageUsers && <Link href="/users" className="text-sm font-semibold text-teal-800 hover:text-teal-950">Manage user accounts and roles</Link>}
            </div>
        </form>
    );
}