import { Link } from '@inertiajs/react';
import { Field, inputClass } from '../../Pages/Auth/Login';
import { AUDIENCES } from '../../lib/notices';

/**
 * Shared by Notices/Create and Notices/Edit.
 * Create passes onSaveAndPublish (publishers only) to show a second button.
 */
export default function NoticeForm({ form, onSubmit, onSaveAndPublish = null, submitLabel, cancelHref }) {
    return (
        <form onSubmit={onSubmit} className="grid gap-4 sm:grid-cols-2">
            <div className="sm:col-span-2">
                <Field label="Title" error={form.errors.title}>
                    <input value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} className={inputClass(form.errors.title)} maxLength={180} required />
                </Field>
            </div>
            <Field label="Audience" error={form.errors.audience}>
                <select value={form.data.audience} onChange={(event) => form.setData('audience', event.target.value)} className={inputClass(form.errors.audience)}>
                    {AUDIENCES.map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                </select>
            </Field>
            <Field label="Optional expiry date" error={form.errors.expires_at}>
                <input type="date" value={form.data.expires_at} onChange={(event) => form.setData('expires_at', event.target.value)} className={inputClass(form.errors.expires_at)} />
            </Field>
            <div className="sm:col-span-2">
                <Field label="Message" error={form.errors.body}>
                    <textarea value={form.data.body} onChange={(event) => form.setData('body', event.target.value)} className={inputClass(form.errors.body)} rows={8} maxLength={10000} required />
                </Field>
            </div>
            {form.errors.notice && <p role="alert" className="text-sm text-rose-700 sm:col-span-2">{form.errors.notice}</p>}
            <div className="flex flex-wrap gap-3 sm:col-span-2">
                <button type="submit" disabled={form.processing} className="rounded-xl bg-teal-800 px-5 py-3 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-60">{form.processing ? 'Saving…' : submitLabel}</button>
                {onSaveAndPublish && <button type="button" disabled={form.processing} onClick={onSaveAndPublish} className="rounded-xl border border-teal-800 px-5 py-3 text-sm font-semibold text-teal-800 hover:bg-teal-50 disabled:opacity-60">Save and publish</button>}
                <Link href={cancelHref} className="rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</Link>
            </div>
        </form>
    );
}