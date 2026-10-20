import { Link } from '@inertiajs/react';

export default function Pager({ links }) {
    if (!links || links.length <= 3) return null;

    return (
        <nav aria-label="Pagination" className="flex flex-wrap justify-center gap-1 border-t border-slate-100 px-4 py-4">
            {links.map((link, index) => (
                <Link
                    key={`${link.label}-${index}`}
                    href={link.url || '#'}
                    preserveScroll
                    preserveState
                    aria-current={link.active ? 'page' : undefined}
                    className={`rounded-lg px-3 py-2 text-sm ${link.active ? 'bg-teal-800 font-semibold text-white' : 'text-slate-600 hover:bg-slate-100'} ${!link.url ? 'pointer-events-none opacity-40' : ''}`}
                >
                    {link.label.replace(/<[^>]*>/g, '').replaceAll('&laquo;', '‹').replaceAll('&raquo;', '›')}
                </Link>
            ))}
        </nav>
    );
}