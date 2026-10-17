import { STAGES, stageLabel, statusTone } from '../../lib/admissions';

export function StatusBadge({ status }) {
    return <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${statusTone(status)}`}>{stageLabel(status)}</span>;
}

/** Labelled tracker: enquiry, application, review, approval, enrolment. */
export function StageTracker({ status }) {
    if (status === 'rejected') {
        return <p className="rounded-xl bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">Application rejected and closed.</p>;
    }

    const activeIndex = STAGES.findIndex(([value]) => value === status);

    return (
        <ol aria-label="Admission progress" className="grid gap-2 sm:grid-cols-5">
            {STAGES.map(([value, label], index) => {
                const done = index < activeIndex || status === 'enrolled';
                const current = index === activeIndex && status !== 'enrolled';

                return (
                    <li
                        key={value}
                        aria-current={current ? 'step' : undefined}
                        className={`rounded-xl border px-3 py-2.5 text-xs font-semibold ${done ? 'border-teal-200 bg-teal-50 text-teal-900' : current ? 'border-amber-300 bg-amber-50 text-amber-900' : 'border-slate-200 bg-white text-slate-400'}`}
                    >
                        <span className="block text-[11px] font-medium opacity-70">{done ? 'Done' : current ? 'Current' : 'Pending'}</span>
                        {label}
                    </li>
                );
            })}
        </ol>
    );
}