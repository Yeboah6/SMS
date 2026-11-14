import { Link } from '@inertiajs/react';

export default function Pagination({ links, from, to, total }) {
    if (!links || links.length <= 3) {
        return null;
    }

    return (
        <nav aria-label="Pagination" className="flex flex-col gap-3 border-t border-slate-100 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
            {Number.isInteger(total) && (
                <p className="text-center text-xs text-slate-500 sm:text-left">
                    Showing {from ?? 0} to {to ?? 0} of {total.toLocaleString()} results
                </p>
            )}
            <div className="flex flex-wrap justify-center gap-1">
                {links.map((link, index) => {
                    const label = link.label
                        .replace(/<[^>]*>/g, '')
                        .replaceAll('&laquo;', '‹')
                        .replaceAll('&raquo;', '›');
                    const className = `rounded-lg px-3 py-2 text-sm ${
                        link.active
                            ? 'bg-teal-800 font-semibold text-white ring-1 ring-inset ring-teal-900/10'
                            : 'text-slate-600 hover:bg-slate-100'
                    } ${!link.url ? 'pointer-events-none opacity-40' : ''}`;

                    return link.url ? (
                        <Link
                            key={`${link.label}-${index}`}
                            href={link.url}
                            preserveScroll
                            preserveState
                            aria-current={link.active ? 'page' : undefined}
                            aria-label={label}
                            className={`${className} focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-700 focus-visible:ring-offset-2`}
                        >
                            {label}
                        </Link>
                    ) : (
                        <span key={`${link.label}-${index}`} aria-disabled="true" className={className}>
                            {label}
                        </span>
                    );
                })}
            </div>
        </nav>
    );
}
