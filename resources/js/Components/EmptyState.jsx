import { Inbox } from 'lucide-react';

export default function EmptyState({ title, detail, children }) {
    return (
        <div className="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center shadow-sm sm:py-14">
            <span className="mx-auto flex size-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-500">
                <Inbox size={22} strokeWidth={1.8} aria-hidden="true" />
            </span>
            <p className="mt-4 font-semibold text-slate-900">{title}</p>
            <p className="mx-auto mt-1 max-w-md text-sm leading-6 text-slate-500">{detail}</p>
            {children && <div className="mt-5">{children}</div>}
        </div>
    );
}