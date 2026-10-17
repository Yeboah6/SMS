export const AUDIENCES = [
    ['all', 'Everyone'],
    ['staff', 'All staff'],
    ['teachers', 'Teachers'],
    ['students', 'Students'],
    ['parents', 'Parents'],
];

export const audienceLabel = (value) => AUDIENCES.find(([key]) => key === value)?.[1] || value;

export function stateOf(notice) {
    if (notice.expired) return 'expired';
    return notice.status;
}

export const STATE_LABELS = { draft: 'Draft', published: 'Published', expired: 'Expired' };

export function stateTone(state) {
    if (state === 'published') return 'bg-emerald-50 text-emerald-800';
    if (state === 'draft') return 'bg-amber-50 text-amber-800';
    return 'bg-slate-100 text-slate-600';
}

export const dateTimeLabel = (value) => (value ? new Date(value.replace(' ', 'T')).toLocaleString() : '');
export const dateLabel = (value) => (value ? new Date(`${value}T00:00:00`).toLocaleDateString() : '');