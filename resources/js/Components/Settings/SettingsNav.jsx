import { Link } from '@inertiajs/react';

const AREAS = [
    ['overview', 'Overview', '/settings'],
    ['school', 'School profile', '/settings/school'],
    ['grading', 'Grading & attendance', '/settings/grading'],
    ['fees', 'Fee defaults', '/settings/fees'],
    ['notifications', 'Notifications', '/settings/notifications'],
];

/** One tab per settings area. Only the overview is read-only; the others are edit pages. */
export default function SettingsNav({ current, canUpdate = true }) {
    const areas = canUpdate ? AREAS : AREAS.slice(0, 1);

    return (
        <nav aria-label="Settings" className="mb-6 flex gap-2 overflow-x-auto rounded-2xl border border-slate-200/80 bg-white/90 p-2 shadow-sm">
            {areas.map(([key, label, href]) => (
                <Link key={key} href={href} aria-current={current === key ? 'page' : undefined} className={`shrink-0 rounded-xl px-4 py-2.5 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-700 focus-visible:ring-offset-2 ${current === key ? 'bg-teal-800 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'}`}>
                    {label}
                </Link>
            ))}
        </nav>
    );
}