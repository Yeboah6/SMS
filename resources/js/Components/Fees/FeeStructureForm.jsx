import { Link } from '@inertiajs/react';
import { Field, inputClass } from '../../Pages/Auth/Login';

/**
 * Shared by FeeStructures/Create and FeeStructures/Edit.
 * On edit the class and fee head are fixed (change them by deleting and adding a structure).
 */
export default function FeeStructureForm({ form, onSubmit, classes = [], feeHeads = [], fixed = null, cancelHref, submitLabel }) {
    return (
        <form onSubmit={onSubmit} className="grid gap-4 sm:grid-cols-2">
            {fixed ? (
                <>
                    <div><p className="text-sm font-medium text-slate-700">Class</p><p className="mt-2 text-sm text-slate-900">{fixed.className}</p></div>
                    <div><p className="text-sm font-medium text-slate-700">Fee head</p><p className="mt-2 text-sm text-slate-900">{fixed.feeHeadName}</p></div>
                </>
            ) : (
                <>
                    <Field label="Class" error={form.errors.class_id}>
                        <select value={form.data.class_id} onChange={(event) => form.setData('class_id', event.target.value)} className={inputClass(form.errors.class_id)} required>
                            <option value="">Select class</option>
                            {classes.map((schoolClass) => <option key={schoolClass.id} value={schoolClass.id}>{schoolClass.name}</option>)}
                        </select>
                    </Field>
                    <Field label="Fee head" error={form.errors.fee_head_id}>
                        <select value={form.data.fee_head_id} onChange={(event) => form.setData('fee_head_id', event.target.value)} className={inputClass(form.errors.fee_head_id)} required>
                            <option value="">Select fee</option>
                            {feeHeads.map((head) => <option key={head.id} value={head.id}>{head.name}</option>)}
                        </select>
                    </Field>
                </>
            )}
            <Field label="Amount" error={form.errors.amount}>
                <input type="number" min="0.01" step="0.01" value={form.data.amount} onChange={(event) => form.setData('amount', event.target.value)} className={inputClass(form.errors.amount)} required />
            </Field>
            <Field label="Due date" error={form.errors.due_date}>
                <input type="date" value={form.data.due_date} onChange={(event) => form.setData('due_date', event.target.value)} className={inputClass(form.errors.due_date)} required />
            </Field>
            {form.errors.academic_year_id && <p role="alert" className="text-sm text-rose-700 sm:col-span-2">{form.errors.academic_year_id}</p>}
            {!fixed && form.errors.fee_head_id === undefined && <p className="text-xs text-slate-500 sm:col-span-2">Each fee head can appear once per class and year.</p>}
            <div className="flex flex-wrap gap-3 sm:col-span-2">
                <button type="submit" disabled={form.processing} className="rounded-xl bg-teal-800 px-5 py-3 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-60">{form.processing ? 'Saving…' : submitLabel}</button>
                <Link href={cancelHref} className="rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</Link>
            </div>
        </form>
    );
}