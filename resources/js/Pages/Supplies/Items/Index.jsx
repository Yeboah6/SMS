import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../../Layouts/AppLayout';
import PageAlerts from '../../../Components/Exams/PageAlerts';
import SuppliesNav from '../../../Components/Supplies/SuppliesNav';
import Pill from '../../../Components/Supplies/Pill';
import Pager from '../../../Components/Supplies/Pager';
import { STOCK_STATUS, useCan, useMoney } from '../../../lib/supplies';

export default function ItemsIndex({ items, filters, categories, totals, can }) {
    const money = useMoney();
    const canLedger = useCan()('supplies.stock.view');
    const [search, setSearch] = useState(filters.search || '');

    function visit(overrides = {}) {
        const params = { search, category: filters.category, stock: filters.stock, state: filters.state, ...overrides };
        if (params.state === 'active') delete params.state;
        Object.keys(params).forEach((key) => !params[key] && delete params[key]);
        router.get('/supplies/items', params, { preserveState: true, replace: true });
    }

    return (
        <AppLayout title="Inventory" description="Every book and supply the school sells, with its stock and price.">
            <Head title="Inventory" />
            <PageAlerts />
            <SuppliesNav current="items" />

            {(totals.low > 0 || totals.out > 0) && (
                <div role="status" className="mb-6 flex flex-wrap items-center gap-x-6 gap-y-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    {totals.out > 0 && <button type="button" onClick={() => visit({ stock: 'out' })} className="font-semibold underline">{totals.out} out of stock</button>}
                    {totals.low > 0 && <button type="button" onClick={() => visit({ stock: 'low' })} className="font-semibold underline">{totals.low} low on stock</button>}
                </div>
            )}

            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div className="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 xl:flex-row xl:items-center xl:justify-between">
                    <h2 className="font-semibold text-slate-900">{items.total.toLocaleString()} item{items.total === 1 ? '' : 's'}</h2>
                    <div className="flex flex-wrap items-center gap-3">
                        <form onSubmit={(event) => { event.preventDefault(); visit(); }} className="flex gap-2">
                            <input type="search" aria-label="Search items" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Name or SKU" className="w-44 rounded-xl border border-slate-200 px-3 py-2 text-sm" />
                            <button className="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Search</button>
                        </form>
                        <select aria-label="Category" value={filters.category} onChange={(event) => visit({ category: event.target.value })} className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                            <option value="">All categories</option>
                            {categories.map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}
                        </select>
                        <select aria-label="Stock level" value={filters.stock} onChange={(event) => visit({ stock: event.target.value })} className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                            <option value="">Any stock level</option><option value="ok">In stock</option><option value="low">Low</option><option value="out">Out of stock</option>
                        </select>
                        <select aria-label="Status" value={filters.state} onChange={(event) => visit({ state: event.target.value })} className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                            <option value="active">Active</option><option value="inactive">Inactive</option><option value="all">All</option>
                        </select>
                        {canLedger && <Link href="/supplies/stock-movements" className="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Stock ledger</Link>}
                        <Link href="/supplies/categories" className="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Categories</Link>
                        {can.create && <Link href="/supplies/items/create" className="rounded-xl bg-teal-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-900">Add item</Link>}
                    </div>
                </div>

                {items.data.length === 0 ? (
                    <p className="px-6 py-14 text-center text-sm text-slate-500">No items match.</p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[860px] text-left text-sm">
                            <thead className="bg-slate-50 text-xs text-slate-500"><tr><th className="px-5 py-3">Item</th><th className="px-5 py-3">Category</th><th className="px-5 py-3 text-right">Cost</th><th className="px-5 py-3 text-right">Price</th><th className="px-5 py-3 text-right">On hand</th><th className="px-5 py-3">Stock</th><th className="px-5 py-3"><span className="sr-only">Actions</span></th></tr></thead>
                            <tbody className="divide-y divide-slate-100">
                                {items.data.map((item) => (
                                    <tr key={item.id} className="hover:bg-slate-50/70">
                                        <td className="px-5 py-3.5"><Link href={`/supplies/items/${item.id}`} className="font-medium text-teal-800 hover:text-teal-950">{item.name}</Link>{!item.isActive && <span className="ml-2 rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600">Inactive</span>}<p className="mt-0.5 text-xs text-slate-500">{item.sku}</p></td>
                                        <td className="px-5 py-3.5 text-slate-600">{item.category}</td>
                                        <td className="px-5 py-3.5 text-right text-slate-600">{money(item.costPrice)}</td>
                                        <td className="px-5 py-3.5 text-right font-medium text-slate-900">{money(item.sellingPrice)}</td>
                                        <td className="px-5 py-3.5 text-right text-slate-900">{item.quantity} <span className="text-xs text-slate-500">{item.unit}</span></td>
                                        <td className="px-5 py-3.5"><Pill map={STOCK_STATUS} value={item.stockStatus} /></td>
                                        <td className="px-5 py-3.5"><div className="flex items-center justify-end gap-3 text-xs font-semibold"><Link href={`/supplies/items/${item.id}`} className="text-teal-800 hover:text-teal-950">View</Link>{can.update && <Link href={`/supplies/items/${item.id}/edit`} className="text-teal-800 hover:text-teal-950">Edit</Link>}{can.adjust && <Link href={`/supplies/items/${item.id}/adjust`} className="text-teal-800 hover:text-teal-950">Adjust</Link>}</div></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                <Pager links={items.links} />
            </section>
        </AppLayout>
    );
}