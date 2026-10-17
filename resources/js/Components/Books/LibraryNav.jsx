import { Link } from '@inertiajs/react';

/** Two areas of the library: what the school owns, and who has borrowed it. */
export default function LibraryNav({ current }) {
    const tabs = [['inventory', 'Books inventory', '/library/books'], ['loans', 'Loans', '/library/loans']];

    return (
        <nav aria-label="Library" className="mb-6 flex gap-2 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-2">
            {tabs.map(([key, label, href]) => (
                <Link key={key} href={href} aria-current={current === key ? 'page' : undefined} className={`shrink-0 rounded-xl px-4 py-2.5 text-sm font-semibold ${current === key ? 'bg-teal-800 text-white' : 'text-slate-600 hover:bg-slate-100'}`}>
                    {label}
                </Link>
            ))}
        </nav>
    );
}