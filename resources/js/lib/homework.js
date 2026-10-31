export const dateLabel = (value) => (value ? new Date(`${value}T00:00:00`).toLocaleDateString() : '—');
export const dateTimeLabel = (value) => (value ? new Date(value.replace(' ', 'T')).toLocaleString() : '—');

export const WINDOW = {
    upcoming: ['Not yet open', 'bg-slate-100 text-slate-700'],
    open: ['Open', 'bg-emerald-50 text-emerald-800'],
    closed: ['Closed', 'bg-amber-50 text-amber-800'],
};

export const MY_STATUS = {
    to_do: ['To do', 'bg-amber-50 text-amber-800'],
    submitted: ['Submitted', 'bg-sky-50 text-sky-800'],
    graded: ['Graded', 'bg-emerald-50 text-emerald-800'],
    missing: ['Missing', 'bg-rose-50 text-rose-800'],
    upcoming: ['Not yet open', 'bg-slate-100 text-slate-700'],
};