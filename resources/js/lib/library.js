export const dateLabel = (value) => new Date(`${value}T00:00:00`).toLocaleDateString();

export function loanTone(status) {
    if (status === 'returned') return 'bg-emerald-50 text-emerald-800';
    if (status === 'overdue') return 'bg-rose-50 text-rose-800';
    return 'bg-amber-50 text-amber-800';
}

export const LOAN_LABELS = { issued: 'On loan', overdue: 'Overdue', returned: 'Returned' };

export function copyTone(status) {
    if (status === 'available') return 'bg-emerald-50 text-emerald-800';
    if (status === 'issued') return 'bg-amber-50 text-amber-800';
    return 'bg-slate-100 text-slate-600';
}