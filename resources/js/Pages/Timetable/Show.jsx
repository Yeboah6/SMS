import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import StatusBanner from '../../Components/StatusBanner';

export default function TimetableShow({ mode, slot, canManage }) {
    const canOpenSection = mode === 'manager' || mode === 'teacher';

    function removeSlot() {
        if (window.confirm(`Remove ${slot.subject} (${slot.startsAt}–${slot.endsAt}) from ${slot.dayName}?`)) {
            router.delete(`/timetable/${slot.id}`);
        }
    }

    return (
        <AppLayout title={`${slot.subject} · ${slot.dayName}`} description={`${slot.startsAt}–${slot.endsAt}`}>
            <Head title={`${slot.subject} · ${slot.dayName}`} />
            <StatusBanner />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <Link href="/timetable" className="text-sm font-semibold text-teal-800 hover:text-teal-950">Back to timetable</Link>
                {canManage && (
                    <div className="flex items-center gap-4 text-sm font-semibold">
                        <Link href={`/timetable/${slot.id}/edit`} className="text-teal-800 hover:text-teal-950">Edit period</Link>
                        <button type="button" onClick={removeSlot} className="text-rose-700 hover:text-rose-900">Remove period</button>
                    </div>
                )}
            </div>

            <section className="max-w-3xl rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <h2 className="font-semibold text-slate-900">Period details</h2>
                <dl className="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                    <Detail label="Day" value={slot.dayName} />
                    <Detail label="Time" value={`${slot.startsAt}–${slot.endsAt}`} />
                    <Detail label="Subject" value={slot.subject} />
                    <Detail label="Teacher" value={slot.teacher} />
                    <div>
                        <dt className="text-xs font-medium text-slate-500">Class and section</dt>
                        <dd className="mt-1 text-slate-900">
                            {canOpenSection
                                ? <Link href={`/sections/${slot.sectionId}`} className="font-medium text-teal-800 hover:text-teal-950">{slot.section}</Link>
                                : slot.section}
                        </dd>
                    </div>
                    <Detail label="Room" value={slot.room || 'Not set'} />
                    <Detail label="Academic year" value={slot.yearName} />
                </dl>
            </section>
        </AppLayout>
    );
}

function Detail({ label, value }) {
    return (
        <div>
            <dt className="text-xs font-medium text-slate-500">{label}</dt>
            <dd className="mt-1 text-slate-900">{value}</dd>
        </div>
    );
}