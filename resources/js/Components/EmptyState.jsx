export default function EmptyState({ title, detail, children }) {
    return (
        <div className="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center">
            <p className="font-medium text-slate-800">{title}</p>
            <p className="mt-1 text-sm text-slate-500">{detail}</p>
            {children && <div className="mt-4">{children}</div>}
        </div>
    );
}