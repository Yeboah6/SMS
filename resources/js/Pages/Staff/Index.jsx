import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import { Field, inputClass } from '../Auth/Login';

export default function StaffIndex({
    staff,
    departments,
    designations,
    teacherUsers,
    canCreate,
    canManageDepartments,
    canManageDesignations,
}) {
    const { status } = usePage().props;
    const [search, setSearch] = useState('');
    const form = useForm({
        staff_number: '',
        name: '',
        email: '',
        phone: '',
        joining_date: '',
        department_id: '',
        designation_id: '',
        user_id: '',
        is_teacher: false,
        status: 'active',
        qualifications: '',
    });

    function createStaff(event) {
        event.preventDefault();
        form.post('/staff', { onSuccess: () => form.reset() });
    }

    function searchStaff(event) {
        event.preventDefault();
        router.get('/staff', { search }, { preserveState: true, replace: true });
    }

    return (
        <AppLayout title="Staff" description="Manage staff profiles and connect teacher accounts to class assignments.">
            <Head title="Staff" />
            {status && <div role="status" className="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{status}</div>}

            {(canManageDepartments || canManageDesignations) && (
                <div className="mb-6 grid gap-4 lg:grid-cols-2">
                    {canManageDepartments && <LookupForm title="Departments" route="/departments" />}
                    {canManageDesignations && <LookupForm title="Designations" route="/designations" />}
                </div>
            )}

            {canCreate && (
                <section className="mb-6 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <h2 className="font-semibold text-slate-900">Create staff profile</h2>
                    <p className="mt-1 text-sm text-slate-500">Teacher profiles must link to an account assigned the Teacher role.</p>
                    <form onSubmit={createStaff} className="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
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
                        <Field label="Account" error={form.errors.user_id}>
                            <select value={form.data.user_id} onChange={(event) => form.setData('user_id', event.target.value)} className={inputClass(form.errors.user_id)}>
                                <option value="">No linked account</option>
                                {teacherUsers.map((user) => <option key={user.id} value={user.id}>{user.name} · {user.email}</option>)}
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
                                <textarea rows={2} value={form.data.qualifications} onChange={(event) => form.setData('qualifications', event.target.value)} className={inputClass(form.errors.qualifications)} />
                            </Field>
                        </div>
                        <label className="flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" checked={form.data.is_teacher} onChange={(event) => form.setData('is_teacher', event.target.checked)} className="size-4 rounded border-slate-300 text-teal-700 focus:ring-teal-700" />
                            This staff member teaches classes
                        </label>
                        <div className="flex items-end xl:col-span-2">
                            <button disabled={form.processing} className="w-full rounded-xl bg-teal-800 px-4 py-3 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-60 sm:w-auto">Create staff profile</button>
                        </div>
                        {form.errors.user_id && <p role="alert" className="text-sm text-rose-700 sm:col-span-2 xl:col-span-3">{form.errors.user_id}</p>}
                    </form>
                </section>
            )}

            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div className="flex flex-col gap-4 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 className="font-semibold text-slate-900">Staff directory</h2>
                        <p className="mt-1 text-sm text-slate-500">{staff.total.toLocaleString()} staff profiles</p>
                    </div>
                    <form onSubmit={searchStaff} className="flex gap-2">
                        <input type="search" aria-label="Search staff" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Name, ID, or email" className="min-w-0 rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-teal-700 focus:outline-none" />
                        <button className="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Search</button>
                    </form>
                </div>
                {staff.data.length === 0 ? (
                    <p className="px-6 py-12 text-center text-sm text-slate-500">No staff profiles match this view.</p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[720px] text-left text-sm">
                            <thead className="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th className="px-5 py-3">Staff</th>
                                    <th className="px-5 py-3">Contact</th>
                                    <th className="px-5 py-3">Department</th>
                                    <th className="px-5 py-3">Designation</th>
                                    <th className="px-5 py-3">Status</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {staff.data.map((member) => (
                                    <tr key={member.id}>
                                        <td className="px-5 py-4">
                                            <p className="font-medium text-slate-900">{member.name}</p>
                                            <p className="mt-0.5 text-xs text-slate-500">{member.staffNumber}{member.isTeacher ? ' · Teacher' : ''}</p>
                                        </td>
                                        <td className="px-5 py-4 text-slate-600">{member.email || member.phone || '—'}</td>
                                        <td className="px-5 py-4 text-slate-600">{member.department || '—'}</td>
                                        <td className="px-5 py-4 text-slate-600">{member.designation || '—'}</td>
                                        <td className="px-5 py-4"><span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${member.status === 'active' ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-600'}`}>{member.status}</span></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                {staff.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-slate-100 px-5 py-3">
                        <button
                            type="button"
                            disabled={staff.current_page <= 1}
                            onClick={() => router.get('/staff', { search, page: staff.current_page - 1 }, { preserveState: true, replace: true })}
                            className="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 disabled:opacity-40"
                        >
                            Previous
                        </button>
                        <span className="text-xs text-slate-500">Page {staff.current_page} of {staff.last_page}</span>
                        <button
                            type="button"
                            disabled={staff.current_page >= staff.last_page}
                            onClick={() => router.get('/staff', { search, page: staff.current_page + 1 }, { preserveState: true, replace: true })}
                            className="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 disabled:opacity-40"
                        >
                            Next
                        </button>
                    </div>
                )}
            </section>
        </AppLayout>
    );
}

function LookupForm({ title, route }) {
    const form = useForm({ name: '', description: '' });

    function submit(event) {
        event.preventDefault();
        form.post(route, { preserveScroll: true, onSuccess: () => form.reset() });
    }

    return (
        <form onSubmit={submit} className="rounded-2xl border border-slate-200 bg-white p-4">
            <h2 className="text-sm font-semibold text-slate-800">Add {title.toLowerCase().slice(0, -1)}</h2>
            <div className="mt-3 flex gap-2">
                <input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} className="min-w-0 flex-1 rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder={`${title.slice(0, -1)} name`} required />
                <button disabled={form.processing} className="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Add</button>
            </div>
            {form.errors.name && <p className="mt-2 text-xs text-rose-700">{form.errors.name}</p>}
        </form>
    );
}
