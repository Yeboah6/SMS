import { SlidersHorizontal } from 'lucide-react';

export default function ReportFilterPanel({ children, description = 'Choose the scope for this report.' }) {
    return (
        <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div className="mb-5 flex items-start gap-3">
                <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-teal-50 text-teal-800">
                    <SlidersHorizontal size={18} aria-hidden="true" />
                </span>
                <div>
                    <h2 className="font-semibold text-slate-900">Report filters</h2>
                    <p className="mt-1 text-sm text-slate-500">{description}</p>
                </div>
            </div>
            {children}
        </section>
    );
}
