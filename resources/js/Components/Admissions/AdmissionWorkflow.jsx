import { useForm } from '@inertiajs/react';
import { Field, inputClass } from '../../Pages/Auth/Login';

/** The stage actions: receive, review, approve or reject, and complete enrolment. */
export default function AdmissionWorkflow({ admission, academicYears, today, can }) {
    const form = useForm({
        review_notes: admission.reviewNotes || '',
        academic_year_id: '',
        class_id: '',
        section_id: '',
        enrolment_date: today,
        assign_fees: false,
        first_payment: false,
        first_payment_amount: '',
        first_payment_method: 'cash',
        first_payment_reference: '',
        first_payment_date: today,
        first_payment_notes: '',
        create_student_account: false,
        student_email: '',
    });
    const selectedYear = academicYears.find((year) => String(year.id) === form.data.academic_year_id);
    const classes = selectedYear?.classes || [];
    const selectedClass = classes.find((schoolClass) => String(schoolClass.id) === form.data.class_id);
    const sections = selectedClass?.sections || [];
    const { status } = admission;

    const canAct = (status === 'enquiry' && can.review)
        || (status === 'application_received' && can.review)
        || (status === 'under_review' && can.approve)
        || (status === 'approved' && can.enrol);

    function transitionTo(next) {
        // transform() does not return the form in the React adapter, so it is called on its own.
        form.transform((data) => ({ ...data, status: next }));
        form.patch(`/admissions/${admission.id}/status`, {
            preserveScroll: true,
            onFinish: () => form.transform((data) => data),
        });
    }

    if (!canAct) {
        return null;
    }

    return (
        <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
            <h2 className="font-semibold text-slate-900">Next step</h2>

            {status === 'under_review' && (
                <div className="mt-4">
                    <Field label="Review notes (required when rejecting)" error={form.errors.review_notes}>
                        <textarea rows={2} value={form.data.review_notes} onChange={(event) => form.setData('review_notes', event.target.value)} className={inputClass(form.errors.review_notes)} />
                    </Field>
                </div>
            )}

            {status === 'approved' && (
                <div className="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <Field label="Academic year" error={form.errors.academic_year_id}>
                        <select value={form.data.academic_year_id} onChange={(event) => form.setData({ ...form.data, academic_year_id: event.target.value, class_id: '', section_id: '' })} className={inputClass(form.errors.academic_year_id)}>
                            <option value="">Select year</option>
                            {academicYears.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                        </select>
                    </Field>
                    <Field label="Class" error={form.errors.class_id}>
                        <select value={form.data.class_id} onChange={(event) => form.setData({ ...form.data, class_id: event.target.value, section_id: '' })} className={inputClass(form.errors.class_id)} disabled={!selectedYear}>
                            <option value="">Select class</option>
                            {classes.map((schoolClass) => <option key={schoolClass.id} value={schoolClass.id}>{schoolClass.name}</option>)}
                        </select>
                    </Field>
                    <Field label="Section" error={form.errors.section_id}>
                        <select value={form.data.section_id} onChange={(event) => form.setData('section_id', event.target.value)} className={inputClass(form.errors.section_id)} disabled={!selectedClass}>
                            <option value="">Select section</option>
                            {sections.map((section) => <option key={section.id} value={section.id}>{section.name}</option>)}
                        </select>
                    </Field>
                    <Field label="Enrolment date" error={form.errors.enrolment_date}>
                        <input type="date" value={form.data.enrolment_date} onChange={(event) => form.setData('enrolment_date', event.target.value)} className={inputClass(form.errors.enrolment_date)} />
                    </Field>

                    {can.assignFees && (
                        <div className="sm:col-span-2 xl:col-span-4">
                            <label className="flex items-start gap-3 text-sm text-slate-700">
                                <input
                                    type="checkbox"
                                    checked={form.data.assign_fees}
                                    onChange={(event) => form.setData({ ...form.data, assign_fees: event.target.checked, first_payment: event.target.checked && can.recordFirstPayment ? form.data.first_payment : false })}
                                    className="mt-0.5 size-4 rounded border-slate-300 text-teal-700 focus:ring-teal-700"
                                />
                                <span>
                                    Create invoice using configured class fees
                                    {selectedClass?.fees?.length > 0 && (
                                        <span className="mt-1 block text-xs text-slate-500">{selectedClass.fees.map((fee) => `${fee.name}: ${fee.amount}`).join(' · ')}</span>
                                    )}
                                </span>
                            </label>
                            {form.errors.assign_fees && <p role="alert" className="mt-1 text-sm text-rose-700">{form.errors.assign_fees}</p>}
                        </div>
                    )}

                    {can.recordFirstPayment && form.data.assign_fees && (
                        <div className="sm:col-span-2 xl:col-span-4">
                            <label className="flex items-center gap-3 text-sm text-slate-700">
                                <input type="checkbox" checked={form.data.first_payment} onChange={(event) => form.setData('first_payment', event.target.checked)} className="size-4 rounded border-slate-300 text-teal-700 focus:ring-teal-700" />
                                Record a first payment now
                            </label>
                            {form.data.first_payment && (
                                <div className="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                    <Field label="Amount" error={form.errors.first_payment_amount}>
                                        <input type="number" min="0.01" step="0.01" value={form.data.first_payment_amount} onChange={(event) => form.setData('first_payment_amount', event.target.value)} className={inputClass(form.errors.first_payment_amount)} />
                                    </Field>
                                    <Field label="Payment method" error={form.errors.first_payment_method}>
                                        <select value={form.data.first_payment_method} onChange={(event) => form.setData('first_payment_method', event.target.value)} className={inputClass(form.errors.first_payment_method)}>
                                            {['cash', 'bank_transfer', 'card', 'mobile_money', 'cheque', 'other'].map((method) => <option key={method} value={method}>{method.replaceAll('_', ' ')}</option>)}
                                        </select>
                                    </Field>
                                    <Field label="Payment date" error={form.errors.first_payment_date}>
                                        <input type="date" max={today} value={form.data.first_payment_date} onChange={(event) => form.setData('first_payment_date', event.target.value)} className={inputClass(form.errors.first_payment_date)} />
                                    </Field>
                                    <Field label="Transaction reference" error={form.errors.first_payment_reference}>
                                        <input value={form.data.first_payment_reference} onChange={(event) => form.setData('first_payment_reference', event.target.value)} className={inputClass(form.errors.first_payment_reference)} />
                                    </Field>
                                    <div className="sm:col-span-2 xl:col-span-4">
                                        <Field label="Payment notes" error={form.errors.first_payment_notes}>
                                            <textarea rows={2} value={form.data.first_payment_notes} onChange={(event) => form.setData('first_payment_notes', event.target.value)} className={inputClass(form.errors.first_payment_notes)} />
                                        </Field>
                                    </div>
                                </div>
                            )}
                        </div>
                    )}

                    <div className="sm:col-span-2 xl:col-span-4">
                        <label className="flex items-center gap-3 text-sm text-slate-700">
                            <input type="checkbox" checked={form.data.create_student_account} onChange={(event) => form.setData('create_student_account', event.target.checked)} className="size-4 rounded border-slate-300 text-teal-700 focus:ring-teal-700" />
                            Create a student portal account and email a secure password setup link
                        </label>
                        {form.data.create_student_account && (
                            <div className="mt-3 max-w-md">
                                <Field label="Student email" error={form.errors.student_email}>
                                    <input type="email" autoComplete="email" value={form.data.student_email} onChange={(event) => form.setData('student_email', event.target.value)} className={inputClass(form.errors.student_email)} />
                                </Field>
                            </div>
                        )}
                    </div>
                </div>
            )}

            <div className="mt-4 flex flex-wrap gap-2">
                {status === 'enquiry' && <ActionButton disabled={form.processing} onClick={() => transitionTo('application_received')}>Mark application received</ActionButton>}
                {status === 'application_received' && <ActionButton disabled={form.processing} onClick={() => transitionTo('under_review')}>Start review</ActionButton>}
                {status === 'under_review' && (
                    <>
                        <ActionButton disabled={form.processing} onClick={() => transitionTo('approved')}>Approve</ActionButton>
                        <ActionButton disabled={form.processing || !form.data.review_notes.trim()} onClick={() => transitionTo('rejected')} secondary>Reject</ActionButton>
                    </>
                )}
                {status === 'approved' && (
                    <ActionButton disabled={form.processing || !form.data.academic_year_id || !form.data.class_id || !form.data.section_id} onClick={() => transitionTo('enrolled')}>Complete enrolment</ActionButton>
                )}
                {form.errors.status && <p role="alert" className="self-center text-sm text-rose-700">{form.errors.status}</p>}
            </div>
        </section>
    );
}

function ActionButton({ children, disabled, onClick, secondary = false }) {
    return (
        <button
            type="button"
            disabled={disabled}
            onClick={onClick}
            className={`rounded-lg px-4 py-2.5 text-sm font-semibold disabled:opacity-50 ${secondary ? 'border border-slate-200 text-slate-700 hover:bg-slate-50' : 'bg-teal-800 text-white hover:bg-teal-900'}`}
        >
            {children}
        </button>
    );
}