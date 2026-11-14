import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowUpRight, Search, ShieldCheck } from 'lucide-react';
import PageAlerts from '../../Components/Exams/PageAlerts';
import AppLayout from '../../Layouts/AppLayout';

export default function PromotionsIndex({ promotions, sourceYears, targetYears, filters }) {
    const form = useForm(filters);

    function search(event) {
        event.preventDefault();
        router.get('/promotions', {
            search: form.data.search,
            source_year_id: form.data.sourceYearId,
            target_year_id: form.data.targetYearId,
            status: form.data.status,
        }, { preserveScroll: true, preserveState: true, replace: true });
    }

    return (
        <AppLayout title="Student promotions" description="Review promotion decisions and their audit history.">
            <Head title="Student promotions" />
            <PageAlerts />

            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div className="flex flex-wrap items-start justify-between gap-4 border-b border-slate-100 px-5 py-5 sm:px-6">
                    <div>
                        <h2 className="font-semibold text-slate-900">Promotion history</h2>
                        <p className="mt-1 text-sm text-slate-500">{promotions.total.toLocaleString()} recorded decisions</p>
                    </div>
                    <Link
                        href="/promotions/create"
                        className="inline-flex items-center gap-2 rounded-xl bg-teal-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-900"
                    >
                        Record decisions
                        <ArrowUpRight size={16} aria-hidden="true" />
                    </Link>
                </div>

                <form onSubmit={search} className="grid gap-3 border-b border-slate-100 p-5 sm:grid-cols-2 lg:grid-cols-5 sm:px-6">
                    <label className="relative sm:col-span-2 lg:col-span-1">
                        <span className="sr-only">Search students</span>
                        <Search size={16} aria-hidden="true" className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                        <input
                            type="search"
                            value={form.data.search}
                            onChange={(event) => form.setData('search', event.target.value)}
                            placeholder="Student or admission no."
                            className="w-full rounded-xl border border-slate-200 py-2.5 pl-9 pr-3 text-sm"
                        />
                    </label>
                    <label>
                        <span className="sr-only">Source year</span>
                        <select
                            value={form.data.sourceYearId}
                            onChange={(event) => form.setData('sourceYearId', event.target.value)}
                            className="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm"
                        >
                            <option value="">All source years</option>
                            {sourceYears.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                        </select>
                    </label>
                    <label>
                        <span className="sr-only">Target year</span>
                        <select
                            value={form.data.targetYearId}
                            onChange={(event) => form.setData('targetYearId', event.target.value)}
                            className="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm"
                        >
                            <option value="">All target years</option>
                            {targetYears.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                        </select>
                    </label>
                    <label>
                        <span className="sr-only">Decision status</span>
                        <select
                            value={form.data.status}
                            onChange={(event) => form.setData('status', event.target.value)}
                            className="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm"
                        >
                            <option value="">All decision states</option>
                            <option value="recorded">Recorded</option>
                            <option value="reversed">Reversed</option>
                        </select>
                    </label>
                    <button className="rounded-xl border border-teal-800 px-4 py-2.5 text-sm font-semibold text-teal-800 hover:bg-teal-50">
                        Apply filters
                    </button>
                </form>

                {promotions.data.length === 0 ? (
                    <div className="px-6 py-14 text-center">
                        <ShieldCheck className="mx-auto text-slate-400" size={26} aria-hidden="true" />
                        <p className="mt-3 font-medium text-slate-800">No promotion decisions found</p>
                        <p className="mt-1 text-sm text-slate-500">Try changing the filters or record decisions for an eligible academic year.</p>
                    </div>
                ) : (
                    <div className="divide-y divide-slate-100">
                        {promotions.data.map((promotion) => (
                            <article key={promotion.id} className="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                                <div className="min-w-0">
                                    <Link href={`/promotions/${promotion.id}`} className="font-semibold text-teal-800 hover:text-teal-950">
                                        {promotion.student}
                                    </Link>
                                    <p className="mt-0.5 text-xs text-slate-500">{promotion.admissionNumber}</p>
                                    <p className="mt-2 text-sm text-slate-600">{promotion.sourceYear} → {promotion.targetYear}</p>
                                </div>
                                <div className="flex flex-wrap items-center gap-2 sm:justify-end">
                                    <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold capitalize text-slate-700">
                                        {promotion.outcome}
                                    </span>
                                    <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${
                                        promotion.reversedAt
                                            ? 'bg-amber-50 text-amber-800'
                                            : 'bg-emerald-50 text-emerald-800'
                                    }`}>
                                        {promotion.reversedAt ? `Reversed ${promotion.reversedAt}` : 'Recorded'}
                                    </span>
                                    <Link
                                        href={`/promotions/${promotion.id}`}
                                        className="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                    >
                                        Details
                                    </Link>
                                </div>
                            </article>
                        ))}
                    </div>
                )}

                <Pagination links={promotions.links} />
            </section>
        </AppLayout>
    );
}

function Pagination({ links }) {
    if (links.length <= 3) {
        return null;
    }

    return (
        <nav aria-label="Promotion history pagination" className="flex flex-wrap justify-center gap-1 border-t border-slate-100 px-4 py-4">
            {links.map((link, index) => (
                <button
                    key={`${link.label}-${index}`}
                    type="button"
                    disabled={!link.url}
                    onClick={() => link.url && router.visit(link.url, { preserveScroll: true })}
                    aria-current={link.active ? 'page' : undefined}
                    className={`rounded-lg px-3 py-2 text-sm ${
                        link.active
                            ? 'bg-teal-800 font-semibold text-white'
                            : 'text-slate-600 hover:bg-slate-100 disabled:text-slate-300'
                    }`}
                >
                    {link.label.replace(/<[^>]*>/g, '').replaceAll('&laquo;', '‹').replaceAll('&raquo;', '›')}
                </button>
            ))}
        </nav>
    );
}
