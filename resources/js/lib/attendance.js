export const STATUSES = [
    ['present', 'Present'],
    ['absent', 'Absent'],
    ['late', 'Late'],
    ['excused', 'Excused'],
];

export const statusLabel = (status) => STATUSES.find(([value]) => value === status)?.[1] || status;

export function statusTone(status) {
    if (status === 'present') return 'bg-emerald-50 text-emerald-800';
    if (status === 'absent') return 'bg-rose-50 text-rose-800';
    if (status === 'late') return 'bg-amber-50 text-amber-800';
    return 'bg-sky-50 text-sky-800';
}

export const dateLabel = (value) => new Date(`${value}T00:00:00`).toLocaleDateString(undefined, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

export function shiftDate(value, days) {
    const date = new Date(`${value}T00:00:00`);
    date.setDate(date.getDate() + days);
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}