import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../../Layouts/AppLayout';
import PageAlerts from '../../../Components/Exams/PageAlerts';
import SuppliesNav from '../../../Components/Supplies/SuppliesNav';
import Pager from '../../../Components/Supplies/Pager';
import { useMoney } from '../../../lib/supplies';

export default function SuppliersIndex({ suppliers, filters, can }) {
    const money = useMoney();
    const [search, setSearch] = useState(filters.search || '');

    function visit(overrides = {}) {
        const params = { search, state: filters.state, ...overrides };
        if (params.state === 'active') delete params.state;
        Object.keys(params).forEach((key) => !params[key] && delete params[key]);
        router.get('/supplies/suppliers', params, { preserveState: true, replace: true });
    }

    return (
        <AppLayout title="Suppliers" description="Who the school buys books and supplies from.">
            <Head title="Suppliers" />
            <PageAlerts />
            <SuppliesNav current="suppliers" />
            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div className="flex flex-col gap-3 border-b border-slate-100 px-4 py-4 sm:px-5 lg:flex-row lg:items-center lg:justify-between">
                    <h2 className="font-semibold text-slate-900">{suppliers.total} supplier{suppliers.total === 1 ? '' : 's'}</h2>
                    <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                        <form onSubmit={(event) => { event.preventDefault(); visit(); }} className="flex w-full gap-2 sm:w-auto">
                            <input
                                type="search"
                                aria-label="Search suppliers"
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder="Name or contact"
                                className="min-w-0 flex-1 rounded-xl border border-slate-200 px-3 py-2.5 text-sm sm:w-48 sm:flex-none"
                            />
                            <button className="shrink-0 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Search</button>
                        </form>
                        <div className="grid grid-cols-2 gap-3 sm:flex sm:items-center">
                            <select
                                aria-label="Status"
                                value={filters.state}
                                onChange={(event) => visit({ state: event.target.value })}
                                className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm sm:w-auto"
                            >
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="all">All</option>
                            </select>
                            {can.create && (
                                <Link href="/supplies/suppliers/create" className="rounded-xl bg-teal-800 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-teal-900">
                                    Add supplier
                                </Link>
                            )}
                        </div>
                    </div>
                </div>

                {suppliers.data.length === 0 ? (
                    <p className="px-6 py-14 text-center text-sm text-slate-500">No suppliers yet.</p>
                ) : (
                    <>
                        {/* Phones and small tablets: one card per supplier */}
                        <ul className="divide-y divide-slate-100 md:hidden">
                            {suppliers.data.map((supplier) => (
                                <li key={supplier.id} className="px-4 py-4">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Link href={`/supplies/suppliers/${supplier.id}`} className="break-words font-semibold text-teal-800 hover:text-teal-950">
                                            {supplier.name}
                                        </Link>
                                        {!supplier.isActive && (
                                            <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600">Inactive</span>
                                        )}
                                    </div>
                                    {(supplier.contactPerson || supplier.phone) && (
                                        <p className="mt-1 text-sm text-slate-600">
                                            {supplier.contactPerson}
                                            {supplier.contactPerson && supplier.phone && <span className="px-1.5 text-slate-300">·</span>}
                                            {supplier.phone && (
                                                <a href={`tel:${supplier.phone}`} className="hover:text-teal-800">{supplier.phone}</a>
                                            )}
                                        </p>
                                    )}
                                    <dl className="mt-3 grid grid-cols-2 gap-3 rounded-xl bg-slate-50 px-3 py-2.5 text-sm">
                                        <div>
                                            <dt className="text-xs text-slate-500">Purchases</dt>
                                            <dd className="mt-0.5 font-medium text-slate-800">{supplier.purchases}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-xs text-slate-500">Spent</dt>
                                            <dd className="mt-0.5 font-medium text-slate-900">{money(supplier.spent)}</dd>
                                        </div>
                                    </dl>
                                    <div className="mt-3 grid grid-cols-2 gap-2 text-sm font-semibold">
                                        <Link href={`/supplies/suppliers/${supplier.id}`} className="rounded-xl border border-slate-200 px-3 py-2.5 text-center text-teal-800 hover:bg-slate-50">
                                            View
                                        </Link>
                                        {can.update && (
                                            <Link href={`/supplies/suppliers/${supplier.id}/edit`} className="rounded-xl border border-slate-200 px-3 py-2.5 text-center text-teal-800 hover:bg-slate-50">
                                                Edit
                                            </Link>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ul>

                        {/* Tablets and desktops: table */}
                        <div className="hidden overflow-x-auto md:block">
                            <table className="w-full min-w-[640px] text-left text-sm">
                                <thead className="bg-slate-50 text-xs text-slate-500">
                                    <tr>
                                        <th className="px-5 py-3">Supplier</th>
                                        <th className="px-5 py-3">Contact</th>
                                        <th className="px-5 py-3 text-right">Purchases</th>
                                        <th className="px-5 py-3 text-right">Spent</th>
                                        <th className="px-5 py-3"><span className="sr-only">Actions</span></th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {suppliers.data.map((supplier) => (
                                        <tr key={supplier.id} className="hover:bg-slate-50/70">
                                            <td className="px-5 py-3.5">
                                                <Link href={`/supplies/suppliers/${supplier.id}`} className="font-medium text-teal-800 hover:text-teal-950">{supplier.name}</Link>
                                                {!supplier.isActive && <span className="ml-2 rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600">Inactive</span>}
                                            </td>
                                            <td className="px-5 py-3.5 text-slate-600">
                                                {supplier.contactPerson || '—'}
                                                <span className="block text-xs text-slate-500">{supplier.phone}</span>
                                            </td>
                                            <td className="px-5 py-3.5 text-right text-slate-700">{supplier.purchases}</td>
                                            <td className="px-5 py-3.5 text-right text-slate-900">{money(supplier.spent)}</td>
                                            <td className="px-5 py-3.5">
                                                <div className="flex items-center justify-end gap-3 text-xs font-semibold">
                                                    <Link href={`/supplies/suppliers/${supplier.id}`} className="text-teal-800 hover:text-teal-950">View</Link>
                                                    {can.update && <Link href={`/supplies/suppliers/${supplier.id}/edit`} className="text-teal-800 hover:text-teal-950">Edit</Link>}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </>
                )}
                <Pager links={suppliers.links} />
            </section>
        </AppLayout>
    );
}