import { Link } from '@inertiajs/react';

export default function SettingsSaveBar({ form, label }) {
    return (
        <div className="flex flex-wrap items-center gap-3">
            <button disabled={form.processing} className="rounded-xl bg-teal-800 px-5 py-3 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-60">{form.processing ? 'Saving…' : label}</button>
            <Link href="/settings" className="rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</Link>
            {Object.keys(form.errors).length > 0 && <p role="alert" className="text-sm text-rose-700">Review the highlighted settings before saving.</p>}
        </div>
    );
}