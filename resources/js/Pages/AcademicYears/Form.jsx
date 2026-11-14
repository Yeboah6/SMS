import { Link } from '@inertiajs/react';
import { Field, inputClass } from '../Auth/Login';

export default function AcademicYearForm({ form, onSubmit, submitLabel, cancelHref = '/academic-years' }) {
    return (
        <form onSubmit={onSubmit} className="space-y-5">
            <Field label="Academic year name" error={form.errors.name}>
                <input
                    value={form.data.name}
                    onChange={(event) => form.setData('name', event.target.value)}
                    className={inputClass(form.errors.name)}
                    placeholder="2026/2027"
                    required
                />
            </Field>
            <div className="grid gap-4 sm:grid-cols-2">
                <Field label="Start date" error={form.errors.start_date}>
                    <input
                        type="date"
                        value={form.data.start_date}
                        onChange={(event) => form.setData('start_date', event.target.value)}
                        className={inputClass(form.errors.start_date)}
                        required
                    />
                </Field>
                <Field label="End date" error={form.errors.end_date}>
                    <input
                        type="date"
                        value={form.data.end_date}
                        onChange={(event) => form.setData('end_date', event.target.value)}
                        className={inputClass(form.errors.end_date)}
                        required
                    />
                </Field>
            </div>
            {form.errors.academic_year && (
                <p role="alert" className="text-sm text-rose-700">{form.errors.academic_year}</p>
            )}
            <div className="flex flex-wrap items-center justify-end gap-3 border-t border-slate-100 pt-5">
                <Link href={cancelHref} className="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Cancel
                </Link>
                <button
                    disabled={form.processing}
                    className="rounded-xl bg-teal-800 px-5 py-2.5 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-60"
                >
                    {submitLabel}
                </button>
            </div>
        </form>
    );
}