import { useMemo, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import PageAlerts from '../../Components/Exams/PageAlerts';

const round1 = (n) => Math.round(n * 10) / 10;

function ordinal(n) {
    const v = n % 100;
    const suffix = v >= 11 && v <= 13 ? 'th' : ({ 1: 'st', 2: 'nd', 3: 'rd' }[n % 10] ?? 'th');
    return `${n}${suffix}`;
}

function remarkFor(total) {
    if (total >= 80) return 'Excellent';
    if (total >= 70) return 'Very Good';
    if (total >= 60) return 'Good';
    if (total >= 50) return 'Credit';
    if (total >= 40) return 'Pass';
    return 'Needs Improvement';
}

const rank = (scores, mine) => 1 + scores.filter((s) => s > mine).length;

// Page field name -> database column
const DETAIL_COLUMNS = {
    closing: 'closing_date',
    nextBegins: 'next_term_begins',
    nextEnds: 'next_term_ends',
    attendance: 'attendance',
    outOf: 'attendance_out_of',
    conduct: 'conduct',
    attitude: 'attitude',
    teacherRemarks: 'class_teacher_remarks',
    headRemarks: 'head_teacher_remarks',
    arrears: 'school_fees_arrears',
    nextFee: 'next_term_fee',
};

export default function ReportCards({ exam, cards, single, can }) {
    const [query, setQuery] = useState('');
    const [saving, setSaving] = useState(false);
    // Unsaved class-score edits keyed by "studentId:subjectId" (raw input strings).
    const [overrides, setOverrides] = useState({});
    // Unsaved detail edits: { [studentId]: { fieldName: value } }.
    const [edits, setEdits] = useState({});

    // Recompute totals, positions and remarks across the WHOLE class so edits rank correctly.
    const computed = useMemo(() => {
        const withTotals = cards.map((card) => ({
            ...card,
            rows: card.rows.map((row) => {
                if (row.examScore == null) return { ...row, total: null };
                const key = `${card.student.id}:${row.subjectId}`;
                const raw = overrides[key];
                const classScore = raw !== undefined ? (raw === '' ? null : Math.min(Math.max(parseFloat(raw) || 0, 0), 30)) : row.classScore;
                return { ...row, classScore, classInput: raw, total: round1((classScore || 0) + row.examScore) };
            }),
        }));

        const bySubject = {};
        withTotals.forEach((c) => c.rows.forEach((r) => {
            if (r.total != null) (bySubject[r.subjectId] ||= []).push(r.total);
        }));

        const grand = withTotals.map((c) => c.rows.reduce((sum, r) => sum + (r.total || 0), 0));
        const graded = withTotals.map((c) => c.rows.some((r) => r.total != null));
        const gradedGrand = grand.filter((_, i) => graded[i]);

        return withTotals.map((c, i) => ({
            ...c,
            rows: c.rows.map((r) => (r.total == null ? r : { ...r, position: ordinal(rank(bySubject[r.subjectId], r.total)), remark: remarkFor(r.total) })),
            grandTotal: round1(grand[i]),
            overallPosition: graded[i] ? ordinal(rank(gradedGrand, grand[i])) : null,
            classSize: gradedGrand.length,
        }));
    }, [cards, overrides]);

    const q = query.trim().toLowerCase();
    const visible = q
        ? computed.filter((c) => c.student.name.toLowerCase().includes(q) || (c.student.admissionNumber || '').toLowerCase().includes(q))
        : computed;

    function setClassScore(studentId, subjectId, value) {
        setOverrides((prev) => ({ ...prev, [`${studentId}:${subjectId}`]: value }));
    }

    function setDetail(studentId, field, value) {
        setEdits((prev) => ({ ...prev, [studentId]: { ...prev[studentId], [field]: value } }));
    }

    const dirty = Object.keys(overrides).length + Object.keys(edits).length;

    function save() {
        const payload = {};

        const scores = Object.entries(overrides).map(([key, value]) => {
            const [studentId, subjectId] = key.split(':');
            return { student_id: Number(studentId), subject_id: Number(subjectId), score: value === '' ? null : value };
        });
        if (scores.length) payload.scores = scores;

        // Only the fields that were edited are sent; everything else on the saved card is untouched.
        const detailRows = Object.entries(edits).map(([studentId, fields]) => {
            const row = { student_id: Number(studentId) };
            Object.entries(fields).forEach(([name, value]) => { row[DETAIL_COLUMNS[name]] = value === '' ? null : value; });
            return row;
        });
        if (detailRows.length) payload.cards = detailRows;

        router.post(`/exams/${exam.id}/report-cards/save`, payload, {
            preserveScroll: true,
            onStart: () => setSaving(true),
            onSuccess: () => { setOverrides({}); setEdits({}); },
            onFinish: () => setSaving(false),
        });
    }

    return (
        <AppLayout title="Report cards" description={`${exam.name} · ${exam.className}`}>
            <Head title={`Report cards – ${exam.name}`} />
            <PageAlerts />
            <style>{`
                @media print {
                    @page { size: A4; margin: 8mm; }
                    body * { visibility: hidden; }
                    .rc-print, .rc-print * { visibility: visible; }
                    .rc-print { position: absolute; left: 0; top: 0; width: 100%; }
                    .rc-sheet { break-after: page; box-shadow: none !important; margin: 0 !important; max-width: none !important; }
                    .rc-sheet:last-child { break-after: auto; }
                    .rc-table-wrap { overflow: visible !important; }
                    .rc-input { background: transparent !important; }
                    .rc-input::-webkit-outer-spin-button, .rc-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
                }
            `}</style>

            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <Link href={`/exams/${exam.id}`} className="text-sm font-semibold text-teal-800 hover:text-teal-950">Back to examination</Link>
                <div className="flex flex-wrap items-center gap-3">
                    {!single && (
                        <input
                            type="search"
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder="Search by name or admission no."
                            className="w-64 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:ring-teal-600"
                        />
                    )}
                    {single && <Link href={`/exams/${exam.id}/report-cards`} className="text-sm font-semibold text-teal-800 hover:text-teal-950">All students</Link>}
                    {can?.saveClassScores && (
                        <button type="button" onClick={save} disabled={dirty === 0 || saving} className="rounded-lg border border-teal-700 px-3 py-2 text-sm font-semibold text-teal-800 hover:bg-teal-50 disabled:opacity-50">
                            {saving ? 'Saving…' : dirty ? `Save changes (${dirty})` : 'Save changes'}
                        </button>
                    )}
                    <button type="button" onClick={() => window.print()} disabled={visible.length === 0} className="rounded-lg bg-teal-700 px-3 py-2 text-sm font-semibold text-white hover:bg-teal-800 disabled:opacity-50">
                        Print {single ? 'report card' : q ? `shown (${visible.length})` : `all (${visible.length})`}
                    </button>
                </div>
            </div>

            {!single && <p className="mb-4 text-xs text-slate-500">Showing {visible.length} of {computed.length} students. Edit class scores and the fields on each card, then press Save. Clearing a saved attendance, arrears, closing date or class score brings back the automatic value.</p>}

            <div className="rc-print space-y-8">
                {visible.length === 0 && <p className="text-sm text-slate-500">{computed.length === 0 ? 'No students found in this class.' : 'No students match your search.'}</p>}
                {visible.map((card) => (
                    <Card
                        key={card.student.id}
                        card={card}
                        exam={exam}
                        values={{ ...card.details, ...edits[card.student.id] }}
                        onClassScore={setClassScore}
                        onDetail={setDetail}
                        canEdit={!!can?.saveClassScores}
                    />
                ))}
            </div>
        </AppLayout>
    );
}

function Card({ card, exam, values, onClassScore, onDetail, canEdit }) {
    const id = card.student.id;
    const f = (name) => values[name] ?? '';
    const set = (name) => (e) => onDetail(id, name, e.target.value);
    const due = (parseFloat(values.arrears) || 0) + (parseFloat(values.nextFee) || 0);

    return (
        <section className="rc-sheet mx-auto max-w-3xl border-2 border-slate-800 bg-white p-4 text-sm text-slate-900 shadow-sm">
            <div className="mb-3 flex items-center justify-center gap-3 text-center">
                {exam.school?.logoUrl && <img src={exam.school.logoUrl} alt="" className="h-14 w-14 object-contain" />}
                <div>
                    <div className="text-base font-bold uppercase tracking-wide">{exam.school?.name}</div>
                    {exam.school?.motto && <div className="text-xs italic text-slate-600">{exam.school.motto}</div>}
                    {exam.school?.contact && <div className="text-xs text-slate-600">{exam.school.contact}</div>}
                    <div className="text-xs text-slate-600">Terminal Report · {exam.name}</div>
                </div>
            </div>
            <div className="mb-2 flex flex-wrap justify-between gap-2 border-b border-slate-300 pb-2 font-semibold">
                <span>{card.student.name}</span>
                <span className="font-normal text-slate-600">{card.student.admissionNumber}</span>
            </div>

            <div className="space-y-2 border-b-2 border-slate-800 pb-3">
                <Line><Field label="Form" value={exam.className} readOnly /><Field label="Term" value={exam.termName || ''} readOnly /><Field label="Year" value={exam.academicYear} readOnly /></Line>
                <Line><Field label="Date of report" value={exam.reportDate} readOnly /><Field label="Closing date" value={f('closing')} onChange={set('closing')} type="date" readOnly={!canEdit} /></Line>
                <Line><Field label="Next term begins" value={f('nextBegins')} onChange={set('nextBegins')} type="date" readOnly={!canEdit} /><Field label="Next term ends" value={f('nextEnds')} onChange={set('nextEnds')} type="date" readOnly={!canEdit} /></Line>
                <Line><Field label="Attendance obtained" value={f('attendance')} onChange={set('attendance')} type="number" readOnly={!canEdit} /><Field label="Out of" value={f('outOf')} onChange={set('outOf')} type="number" readOnly={!canEdit} /></Line>
            </div>

            <div className="rc-table-wrap max-w-full overflow-x-auto">
                <table className="w-full min-w-[42rem] border-collapse text-left">
                    <thead>
                        <tr className="border-b-2 border-slate-800 text-center text-xs font-bold uppercase">
                            <th className="border-r-2 border-slate-800 p-2 text-left">Subject</th>
                            <th className="border-r border-slate-800 p-2">Class score<br />30%</th>
                            <th className="border-r border-slate-800 p-2">Exams score<br />70%</th>
                            <th className="border-r border-slate-800 p-2">Total score<br />100%</th>
                            <th className="border-r border-slate-800 p-2">Position</th>
                            <th className="p-2">Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        {card.rows.map((r) => (
                            <tr key={r.subjectId} className="border-b border-slate-800 text-center">
                                <td className="border-r-2 border-slate-800 p-2 text-left">{r.subject}</td>
                                <td className="border-r border-slate-800 p-1">
                                    {r.examScore != null && (
                                        <input
                                            type="number" min="0" max="30" step="0.1"
                                            value={r.classInput !== undefined ? r.classInput : (r.classScore ?? '')}
                                            onChange={(e) => onClassScore(id, r.subjectId, e.target.value)}
                                            disabled={!canEdit}
                                            title={r.classSaved ? 'Saved class score' : 'Auto-calculated'}
                                            aria-label={`${r.subject} class score`}
                                            className="rc-input w-16 rounded border border-slate-300 bg-slate-50 px-1 py-0.5 text-center text-sm focus:border-teal-600 focus:ring-teal-600 print:border-0"
                                        />
                                    )}
                                </td>
                                <td className="border-r border-slate-800 p-2">{r.examScore ?? ''}</td>
                                <td className="border-r border-slate-800 p-2 font-semibold">{r.total ?? ''}</td>
                                <td className="border-r border-slate-800 p-2">{r.position ?? ''}</td>
                                <td className="p-2">{r.remark ?? ''}</td>
                            </tr>
                        ))}
                        <tr className="border-y-2 border-slate-800 text-center font-bold">
                            <td className="border-r-2 border-slate-800 p-2 text-left">TOTAL</td>
                            <td className="border-r border-slate-800" />
                            <td className="border-r border-slate-800" />
                            <td className="border-r border-slate-800 p-2">{card.grandTotal}</td>
                            <td className="border-r border-slate-800 p-2">{card.overallPosition ? `${card.overallPosition} of ${card.classSize}` : ''}</td>
                            <td />
                        </tr>
                    </tbody>
                </table>
            </div>

            <div className="mt-3 space-y-2">
                <Field label="Conduct" value={f('conduct')} onChange={set('conduct')} block readOnly={!canEdit} />
                <Field label="Attitude" value={f('attitude')} onChange={set('attitude')} block readOnly={!canEdit} />
                <Field label="Class Teacher’s Remarks" value={f('teacherRemarks')} onChange={set('teacherRemarks')} block readOnly={!canEdit} />
                <div className="space-y-2 pt-3 sm:w-2/3">
                    <Field label="School Fees Arrears GH₵" value={f('arrears')} onChange={set('arrears')} type="number" block readOnly={!canEdit} />
                    <Field label="Next Term’s Fee GH₵" value={f('nextFee')} onChange={set('nextFee')} type="number" block readOnly={!canEdit} />
                    <Field label="Total Amount Due GH₵" value={values.arrears || values.nextFee ? due.toFixed(2) : ''} readOnly block />
                </div>
                <Field label="Head Teacher’s Remarks" value={f('headRemarks')} onChange={set('headRemarks')} block readOnly={!canEdit} />
            </div>
        </section>
    );
}

function Line({ children }) {
    return <div className="flex flex-wrap gap-x-6 gap-y-2">{children}</div>;
}

function Field({ label, value, onChange, readOnly, type = 'text', block }) {
    return (
        <label className={`flex items-baseline gap-2 ${block ? 'w-full' : 'flex-1'}`}>
            <span className="whitespace-nowrap font-medium">{label}:</span>
            <input
                type={type}
                value={value}
                onChange={onChange}
                readOnly={readOnly}
                step={type === 'number' ? 'any' : undefined}
                min={type === 'number' ? 0 : undefined}
                className="rc-input min-w-0 flex-1 border-0 border-b border-dotted border-slate-500 bg-slate-50 px-1 py-0.5 text-sm focus:ring-0 print:[appearance:textfield]"
            />
        </label>
    );
}