import { Link } from '@inertiajs/react';
import { Field, inputClass } from '../../Pages/Auth/Login';
import { typeLabel } from '../../lib/certificates';

/** Shared by Certificates/Create (issue directly) and Certificates/Edit (correct a pending request). */
export default function CertificateForm({ form, onSubmit, certificateTypes, submitLabel, cancelHref, showNote = false, studentName = null }) {
    const set = (key) => (event) => form.setData(key, event.target.value);

    return (
        <form onSubmit={onSubmit} className="grid gap-4 sm:grid-cols-2">
            <div>
                <Field label="Student admission number" error={form.errors.admission_number}>
                    <input value={form.data.admission_number} onChange={set('admission_number')} className={inputClass(form.errors.admission_number)} maxLength={50} required />
                </Field>
                {studentName && <p className="mt-1.5 text-xs text-slate-500">Currently {studentName}</p>}
            </div>
            <Field label="Certificate type" error={form.errors.certificate_type}>
                <select value={form.data.certificate_type} onChange={set('certificate_type')} className={inputClass(form.errors.certificate_type)} required>
                    {certificateTypes.map((type) => <option key={type} value={type}>{typeLabel(type)}</option>)}
                </select>
            </Field>
            <div className="sm:col-span-2">
                <Field label="Reason / purpose" error={form.errors.reason}>
                    <textarea rows={3} value={form.data.reason} onChange={set('reason')} className={inputClass(form.errors.reason)} minLength={10} maxLength={2000} required />
                </Field>
            </div>
            {showNote && (
                <div className="sm:col-span-2">
                    <Field label="Office note (optional)" error={form.errors.review_comments}>
                        <textarea rows={2} value={form.data.review_comments} onChange={set('review_comments')} className={inputClass(form.errors.review_comments)} maxLength={2000} />
                    </Field>
                </div>
            )}
            {form.errors.certificate && <p role="alert" className="text-sm text-rose-700 sm:col-span-2">{form.errors.certificate}</p>}
            <div className="flex flex-wrap gap-3 sm:col-span-2">
                <button disabled={form.processing} className="rounded-xl bg-teal-800 px-5 py-3 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-60">
                    {form.processing ? 'Saving…' : submitLabel}
                </button>
                <Link href={cancelHref} className="rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</Link>
            </div>
        </form>
    );
}