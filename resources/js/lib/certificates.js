export const TYPE_LABELS = {
    bonafide: 'Bonafide',
    character: 'Character',
    completion: 'Completion',
    transfer: 'Transfer',
};

export const DOCUMENT_TITLES = {
    bonafide: 'Bonafide Certificate',
    character: 'Character Certificate',
    completion: 'Completion Certificate',
    transfer: 'Transfer Certificate',
};

export const STATUS_TABS = [
    ['pending', 'Pending'],
    ['issued', 'Issued'],
    ['rejected', 'Rejected'],
    ['cancelled', 'Cancelled'],
    ['revoked', 'Revoked'],
];

export const typeLabel = (type) => TYPE_LABELS[type] || type;

export function statusClass(status) {
    if (status === 'issued') return 'bg-emerald-50 text-emerald-700';
    if (status === 'rejected' || status === 'revoked') return 'bg-rose-50 text-rose-700';
    if (status === 'cancelled') return 'bg-slate-100 text-slate-600';
    return 'bg-amber-50 text-amber-800';
}