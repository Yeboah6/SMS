import { Link, router, useHttp } from '@inertiajs/react';
import { ArrowUpRight, LoaderCircle, Search, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

export default function GlobalSearch({ navigation }) {
    const [query, setQuery] = useState('');
    const [groups, setGroups] = useState([]);
    const [isOpen, setIsOpen] = useState(false);
    const [activeIndex, setActiveIndex] = useState(-1);
    const [searchFailed, setSearchFailed] = useState(false);
    const [retryCount, setRetryCount] = useState(0);
    const requestId = useRef(0);
    const containerRef = useRef(null);
    const { setData, get, cancel, processing } = useHttp({ q: '' });
    const normalizedQuery = query.trim();

    useEffect(() => {
        const handlePointerDown = (event) => {
            if (!containerRef.current?.contains(event.target)) {
                setIsOpen(false);
                setActiveIndex(-1);
            }
        };

        document.addEventListener('pointerdown', handlePointerDown);

        return () => document.removeEventListener('pointerdown', handlePointerDown);
    }, []);

    useEffect(() => {
        const searchTerm = normalizedQuery;

        if (searchTerm.length < 2) {
            cancel();
            setGroups([]);
            setSearchFailed(false);

            return undefined;
        }

        setGroups([]);
        setSearchFailed(false);

        const timeout = window.setTimeout(() => {
            const currentRequestId = ++requestId.current;
            setData('q', searchTerm);

            get('/global-search')
                .then((response) => {
                    if (currentRequestId === requestId.current) {
                        if (Array.isArray(response?.groups)) {
                            setGroups(response.groups);
                        } else {
                            setSearchFailed(true);
                        }
                    }
                })
                .catch(() => {
                    if (currentRequestId === requestId.current) {
                        setSearchFailed(true);
                    }
                });
        }, 250);

        return () => {
            window.clearTimeout(timeout);
            requestId.current += 1;
            cancel();
        };
    }, [cancel, get, normalizedQuery, retryCount, setData]);

    const pageResults = useMemo(() => {
        const pages = navigation.flatMap((item) => item.children ?? [item])
            .filter((item) => item.href && item.label.toLowerCase().includes(normalizedQuery.toLowerCase()));
        const seenHrefs = new Set();

        return pages
            .filter((item) => {
                if (seenHrefs.has(item.href)) {
                    return false;
                }

                seenHrefs.add(item.href);

                return true;
            })
            .slice(0, 5)
            .map((item) => ({
                type: 'page',
                id: item.href,
                title: item.label,
                description: 'Open module',
                href: item.href,
            }));
    }, [navigation, normalizedQuery]);

    const displayGroups = useMemo(() => {
        const visibleGroups = [
            ...(pageResults.length > 0 ? [{ label: 'Pages', viewAll: null, results: pageResults }] : []),
            ...groups,
        ];
        let optionIndex = 0;

        return visibleGroups.map((group) => {
            const results = [
                ...group.results,
                ...(group.viewAll
                    ? [{
                        type: 'view-all',
                        id: `view-all-${group.label}`,
                        title: `View all ${group.label}`,
                        description: null,
                        href: group.viewAll,
                    }]
                    : []),
            ];

            return {
                ...group,
                results: results.map((result) => ({
                    ...result,
                    optionIndex: optionIndex++,
                })),
            };
        });
    }, [groups, pageResults]);

    const allResults = useMemo(
        () => displayGroups.flatMap((group) => group.results),
        [displayGroups],
    );

    useEffect(() => {
        setActiveIndex(-1);
    }, [displayGroups]);

    function closeSearch() {
        setIsOpen(false);
        setActiveIndex(-1);
    }

    function clearSearch() {
        setQuery('');
        setGroups([]);
        setSearchFailed(false);
        setActiveIndex(-1);
        setIsOpen(true);
    }

    function handleKeyDown(event) {
        if (event.key === 'Escape') {
            closeSearch();
        } else if (event.key === 'ArrowDown' && allResults.length > 0) {
            event.preventDefault();
            setActiveIndex((current) => (current + 1) % allResults.length);
        } else if (event.key === 'ArrowUp' && allResults.length > 0) {
            event.preventDefault();
            setActiveIndex((current) => (current <= 0 ? allResults.length - 1 : current - 1));
        } else if (event.key === 'Enter' && allResults[activeIndex]) {
            event.preventDefault();
            router.visit(allResults[activeIndex].href);
            closeSearch();
        }
    }

    return (
        <div ref={containerRef} className="relative min-w-0 w-[clamp(7.5rem,38vw,24rem)] sm:w-full sm:max-w-sm">
            <div className="flex h-10 items-center gap-2 rounded-xl border border-slate-200/80 bg-slate-50/80 px-3 text-slate-500 transition focus-within:border-teal-300 focus-within:bg-white focus-within:ring-2 focus-within:ring-teal-700/15">
                <Search size={17} aria-hidden="true" className="shrink-0 text-slate-400" />
                <input
                    type="search"
                    value={query}
                    maxLength={100}
                    placeholder="Search anything..."
                    role="combobox"
                    aria-label="Search accessible records and modules"
                    aria-autocomplete="list"
                    aria-haspopup="listbox"
                    aria-expanded={isOpen && normalizedQuery.length >= 2}
                    aria-controls={isOpen && normalizedQuery.length >= 2 ? 'global-search-listbox' : undefined}
                    aria-activedescendant={activeIndex >= 0 ? `global-search-result-${activeIndex}` : undefined}
                    className="min-w-0 w-full border-0 bg-transparent p-0 text-sm text-slate-800 outline-none placeholder:text-slate-400 focus:ring-0"
                    onChange={(event) => {
                        setQuery(event.target.value);
                        setActiveIndex(-1);
                        setIsOpen(true);
                    }}
                    onFocus={() => setIsOpen(true)}
                    onKeyDown={handleKeyDown}
                />
                {processing && (
                    <LoaderCircle size={16} aria-label="Searching" className="shrink-0 animate-spin text-teal-700" />
                )}
                {!processing && query && (
                    <button
                        type="button"
                        aria-label="Clear search"
                        className="shrink-0 rounded-md p-1 text-slate-400 transition hover:bg-slate-200 hover:text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-700"
                        onClick={clearSearch}
                    >
                        <X size={14} />
                    </button>
                )}
            </div>

            {isOpen && normalizedQuery.length > 0 && (
                <div
                    id="global-search-results"
                    role="region"
                    aria-label="Search results"
                    className="absolute right-0 top-full z-50 mt-2 max-h-[min(70vh,28rem)] w-[min(90vw,32rem)] overflow-y-auto rounded-2xl border border-slate-200 bg-white p-2 shadow-2xl shadow-slate-900/15 sm:left-0 sm:right-auto sm:w-[min(80vw,32rem)]"
                >
                    {normalizedQuery.length < 2 ? (
                        <p role="status" className="px-3 py-4 text-sm text-slate-500">Type at least 2 characters to search.</p>
                    ) : (
                        <>
                            <div id="global-search-listbox" role="listbox" aria-label="Search results">
                                {displayGroups.map((group) => (
                                    <div key={group.label} role="group" aria-label={group.label} className="py-1">
                                        <p role="presentation" aria-hidden="true" className="px-3 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                                            {group.label}
                                        </p>
                                        {group.results.map((result) => (
                                            <Link
                                                id={`global-search-result-${result.optionIndex}`}
                                                key={`${group.label}-${result.type}-${result.id}`}
                                                href={result.href}
                                                role="option"
                                                aria-selected={result.optionIndex === activeIndex}
                                                className={`flex items-center justify-between gap-3 rounded-xl px-3 py-2.5 text-left transition ${
                                                    result.optionIndex === activeIndex
                                                        ? 'bg-teal-50 text-teal-950'
                                                        : result.type === 'view-all'
                                                            ? 'text-sm font-medium text-teal-700 hover:bg-teal-50'
                                                            : 'text-slate-700 hover:bg-slate-50'
                                                }`}
                                                onMouseEnter={() => setActiveIndex(result.optionIndex)}
                                                onClick={closeSearch}
                                            >
                                                <span className="min-w-0">
                                                    <span className="block truncate text-sm font-medium">{result.title}</span>
                                                    {result.description && (
                                                        <span className="mt-0.5 block truncate text-xs text-slate-500">
                                                            {result.description}
                                                        </span>
                                                    )}
                                                </span>
                                                <ArrowUpRight size={15} aria-hidden="true" className="shrink-0 text-slate-400" />
                                            </Link>
                                        ))}
                                    </div>
                                ))}
                            </div>

                            {processing && (
                                <p role="status" className="px-3 py-4 text-sm text-slate-500">Searching...</p>
                            )}
                            {searchFailed && (
                                <div role="alert" className="flex items-center justify-between gap-3 px-3 py-3">
                                    <p className="text-sm text-rose-700">Search could not be completed.</p>
                                    <button
                                        type="button"
                                        className="text-sm font-semibold text-teal-700 hover:text-teal-900"
                                        onClick={() => setRetryCount((count) => count + 1)}
                                    >
                                        Try again
                                    </button>
                                </div>
                            )}
                            {!processing && !searchFailed && allResults.length === 0 && (
                                <p className="px-3 py-4 text-sm text-slate-500">No matching accessible records or modules.</p>
                            )}
                        </>
                    )}
                </div>
            )}
        </div>
    );
}
