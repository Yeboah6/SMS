/** The main path an application follows. Rejected is a closed branch from review. */
export const STAGES = [
    ['enquiry', 'Enquiry'],
    ['application_received', 'Application received'],
    ['under_review', 'Under review'],
    ['approved', 'Approved'],
    ['enrolled', 'Enrolled'],
];

export const ALL_STATUSES = [...STAGES, ['rejected', 'Rejected']];

export const SOURCE_OPTIONS = [
    ['walk_in', 'Walk-in'],
    ['website', 'Website'],
    ['referral', 'Referral'],
    ['phone', 'Phone'],
    ['social_media', 'Social media'],
];

export const GENDER_OPTIONS = [
    ['female', 'Female'],
    ['male', 'Male'],
    ['other', 'Other'],
    ['prefer_not_to_say', 'Prefer not to say'],
];

export const stageLabel = (status) => ALL_STATUSES.find(([value]) => value === status)?.[1] || status;
export const sourceLabel = (source) => SOURCE_OPTIONS.find(([value]) => value === source)?.[1] || source;
export const humanize = (value) => String(value ?? '').replaceAll('_', ' ');

export function statusTone(status) {
    if (status === 'approved' || status === 'enrolled') return 'bg-emerald-50 text-emerald-800';
    if (status === 'rejected') return 'bg-rose-50 text-rose-700';
    return 'bg-amber-50 text-amber-800';
}