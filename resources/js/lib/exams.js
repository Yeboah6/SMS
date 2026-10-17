export const EXAM_TYPES = ['unit_test', 'mid_term', 'final', 'practical', 'other'];

export function humanize(value) {
    return String(value ?? '').replaceAll('_', ' ');
}

export function dateLabel(date) {
    return new Date(`${date}T00:00:00`).toLocaleDateString();
}

export function statusTone(status) {
    if (status === 'published' || status === 'pass') return 'bg-emerald-50 text-emerald-800';
    if (status === 'fail') return 'bg-rose-50 text-rose-800';
    if (status === 'absent') return 'bg-amber-50 text-amber-800';
    return 'bg-slate-100 text-slate-700';
}