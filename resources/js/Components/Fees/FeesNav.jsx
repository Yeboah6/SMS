import { Link } from '@inertiajs/react';

const STEPS = [
    ['heads', 1, 'Fee heads', '/fee-heads', false],
    ['structures', 2, 'Fee structures', '/fee-structures', true],
    ['invoices', 3, 'Invoices', '/invoices', true],
    ['payments', 4, 'Payments', '/payments', true],
];

/** The fee workflow on every staff page: set up charges, price classes, bill students, collect payments. */
export default function FeesNav({ current, yearId = null }) {
    const suffix = yearId ? `?academic_year_id=${yearId}` : '';

    return (
        <nav aria-label="Fee workflow" className="mb-6 flex gap-2 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-2">
            <Link href="/fees" aria-current={current === 'overview' ? 'page' : undefined} className={`shrink-0 rounded-xl px-4 py-2.5 text-sm font-semibold ${current === 'overview' ? 'bg-teal-800 text-white' : 'text-slate-600 hover:bg-slate-100'}`}>
                Overview
            </Link>
            {STEPS.map(([key, number, label, href, scoped]) => (
                <Link
                    key={key}
                    href={`${href}${scoped ? suffix : ''}`}
                    aria-current={current === key ? 'page' : undefined}
                    className={`flex shrink-0 items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold ${current === key ? 'bg-teal-800 text-white' : 'text-slate-600 hover:bg-slate-100'}`}
                >
                    <span className={`flex size-5 items-center justify-center rounded-full text-[11px] ${current === key ? 'bg-white/20' : 'bg-slate-200 text-slate-600'}`}>{number}</span>
                    {label}
                </Link>
            ))}
        </nav>
    );
}