import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import PageAlerts from '../Exams/PageAlerts';
import Pagination from '../Pagination';

/** The staff filed under one department or designation. `otherLabel` names the second column. */
export default function LookupShow({ noun, baseUrl, listLabel, otherLabel, item, staff }) {
    return (
        <AppLayout title={item.name} description={`Staff in this ${noun}`}>
            <Head title={item.name} />
            <PageAlerts />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <Link href={baseUrl} className="text-sm font-semibold text-teal-800 hover:text-teal-950">Back to {listLabel}</Link>
                <Link href={baseUrl} className="text-sm font-semibold text-teal-800 hover:text-teal-950">Rename or delete</Link>
            </div>

            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div className="border-b border-slate-100 px-5 py-4"><h2 className="font-semibold text-slate-900">{staff.total.toLocaleString()} staff member{staff.total === 1 ? '' : 's'}</h2></div>
                {staff.data.length === 0 ? (
                    <p className="px-6 py-12 text-center text-sm text-slate-500">No staff are assigned to this {noun}. Assign one by editing a staff profile.</p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[560px] text-left text-sm">
                            <thead className="bg-slate-50 text-xs text-slate-500"><tr><th className="px-5 py-3">Staff</th><th className="px-5 py-3">{otherLabel}</th><th className="px-5 py-3">Teaching</th><th className="px-5 py-3">Status</th></tr></thead>
                            <tbody className="divide-y divide-slate-100">
                                {staff.data.map((member) => (
                                    <tr key={member.id}>
                                        <td className="px-5 py-3"><Link href={`/staff/${member.id}`} className="font-medium text-slate-900 hover:text-teal-800">{member.name}</Link><p className="text-xs text-slate-500">{member.staffNumber}</p></td>
                                        <td className="px-5 py-3 text-slate-600">{member.other || '—'}</td>
                                        <td className="px-5 py-3 text-slate-600">{member.isTeacher ? 'Teacher' : 'Non-teaching'}</td>
                                        <td className="px-5 py-3"><span className={`rounded-full px-2.5 py-1 text-xs font-semibold capitalize ${member.status === 'active' ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-600'}`}>{member.status}</span></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                <Pagination links={staff.links} from={staff.from} to={staff.to} total={staff.total} />
            </section>
        </AppLayout>
    );
}