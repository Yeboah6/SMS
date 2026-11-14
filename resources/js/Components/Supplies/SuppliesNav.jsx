import { Link } from '@inertiajs/react';
import { useCan } from '../../lib/supplies';

const TABS = [
    ['dashboard', 'Dashboard', '/supplies', 'supplies.dashboard.view'],
    ['items', 'Inventory', '/supplies/items', 'supplies.items.view'],
    ['sales', 'Sales', '/supplies/sales', 'supplies.sales.view'],
    ['purchases', 'Purchases', '/supplies/purchases', 'supplies.purchases.view'],
    ['suppliers', 'Suppliers', '/supplies/suppliers', 'supplies.suppliers.view'],
    ['requirements', 'Class requirements', '/supplies/requirements', 'supplies.requirements.view'],
    ['reports', 'Reports', '/supplies/reports', 'supplies.reports.view'],
];

/** The areas of the supplies module the signed-in user is allowed into. */
export default function SuppliesNav({ current }) {
    const can = useCan();

    return (
        <nav aria-label="Supplies" className="mb-6 flex gap-2 overflow-x-auto rounded-2xl border border-slate-200/80 bg-white/90 p-2 shadow-sm">
            {TABS.filter(([, , , permission]) => can(permission)).map(([key, label, href]) => (
                <Link key={key} href={href} aria-current={current === key ? 'page' : undefined} className={`shrink-0 rounded-xl px-4 py-2.5 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-700 focus-visible:ring-offset-2 ${current === key ? 'bg-teal-800 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'}`}>
                    {label}
                </Link>
            ))}
        </nav>
    );
}