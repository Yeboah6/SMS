import { Link } from '@inertiajs/react';
import { Bell, ChevronRight, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

const publishedAtFormat = new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
});

export default function NoticeBell({ notices }) {
    const [isOpen, setIsOpen] = useState(false);
    const panelRef = useRef(null);

    useEffect(() => {
        if (!isOpen) {
            return undefined;
        }

        function closeOnOutsideClick(event) {
            if (!panelRef.current?.contains(event.target)) {
                setIsOpen(false);
            }
        }

        function closeOnEscape(event) {
            if (event.key === 'Escape') {
                setIsOpen(false);
            }
        }

        document.addEventListener('pointerdown', closeOnOutsideClick);
        document.addEventListener('keydown', closeOnEscape);

        return () => {
            document.removeEventListener('pointerdown', closeOnOutsideClick);
            document.removeEventListener('keydown', closeOnEscape);
        };
    }, [isOpen]);

    if (notices === null) {
        return null;
    }

    return (
        <div ref={panelRef} className="relative">
            <button
                type="button"
                aria-label="Show notices"
                aria-expanded={isOpen}
                aria-controls="recent-notices-panel"
                onClick={() => setIsOpen((open) => !open)}
                className={`relative rounded-xl p-2.5 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-700 focus-visible:ring-offset-2 ${
                    isOpen ? 'bg-teal-50 text-teal-800' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-800'
                }`}
            >
                <Bell size={19} aria-hidden="true" />
            </button>

            {isOpen && (
                <section
                    id="recent-notices-panel"
                    aria-label="Recent notices"
                    className="absolute right-0 z-50 mt-3 w-[min(22rem,calc(100vw-2rem))] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl shadow-slate-900/10 sm:w-96"
                >
                    <div className="flex items-start justify-between gap-3 border-b border-slate-100 px-4 py-4">
                        <div>
                            <h2 className="text-sm font-semibold text-slate-900">Recent notices</h2>
                            <p className="mt-1 text-xs text-slate-500">Notices for your account</p>
                        </div>
                        <button
                            type="button"
                            aria-label="Close notices"
                            onClick={() => setIsOpen(false)}
                            className="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-700"
                        >
                            <X size={16} aria-hidden="true" />
                        </button>
                    </div>

                    {notices.length > 0 ? (
                        <ul className="max-h-[min(65vh,28rem)] divide-y divide-slate-100 overflow-y-auto">
                            {notices.map((notice) => (
                                <li key={notice.id}>
                                    <Link
                                        href={`/notices/${notice.id}`}
                                        onClick={() => setIsOpen(false)}
                                        className="block px-4 py-3.5 transition hover:bg-slate-50 focus-visible:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-teal-700"
                                    >
                                        <span className="block truncate text-sm font-semibold text-slate-800">{notice.title}</span>
                                        <span className="mt-1 line-clamp-2 block text-xs leading-5 text-slate-600">{notice.excerpt}</span>
                                        <time dateTime={notice.publishedAt} className="mt-2 block text-[11px] font-medium text-slate-400">
                                            {publishedAtFormat.format(new Date(notice.publishedAt))}
                                        </time>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <div className="px-5 py-9 text-center">
                            <span className="mx-auto flex size-10 items-center justify-center rounded-xl bg-slate-100 text-slate-400">
                                <Bell size={18} aria-hidden="true" />
                            </span>
                            <p className="mt-3 text-sm font-semibold text-slate-800">No notices yet</p>
                            <p className="mt-1 text-xs leading-5 text-slate-500">New notices for your account will appear here.</p>
                        </div>
                    )}

                    <div className="border-t border-slate-100 p-2">
                        <Link
                            href="/notices"
                            onClick={() => setIsOpen(false)}
                            className="flex items-center justify-between rounded-xl px-3 py-2.5 text-sm font-semibold text-teal-800 transition hover:bg-teal-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-700"
                        >
                            View all notices
                            <ChevronRight size={16} aria-hidden="true" />
                        </Link>
                    </div>
                </section>
            )}
        </div>
    );
}
