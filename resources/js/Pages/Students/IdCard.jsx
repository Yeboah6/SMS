import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

export default function StudentIdCard({ student, schoolName, schoolLogo, photo, qrCode, backUrl }) {
    return (
        <>
            <Head title={`ID card · ${student.name}`} />
            <style>{`
                @media print {
                    @page { margin: 0; size: 86mm 54mm; }
                    body * { visibility: hidden; }
                    .id-card-print, .id-card-print * { visibility: visible; }
                    .id-card-print {
                        position: absolute; left: 0; top: 0;
                        width: 86mm; height: 54mm;
                        margin: 0; padding: 0;
                        box-shadow: none !important;
                        border-radius: 0 !important;
                        -webkit-print-color-adjust: exact;
                        print-color-adjust: exact;
                    }
                }
            `}</style>

            <AppLayout title="Student ID card" description="Preview and print the student identification card.">
                <div className="mb-5 flex flex-wrap items-center justify-between gap-3 print:hidden">
                    <Link href={backUrl} className="text-sm font-semibold text-teal-800 hover:text-teal-950">
                        ← Back to profile
                    </Link>
                    <button
                        type="button"
                        onClick={() => window.print()}
                        className="rounded-xl bg-teal-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-900"
                    >
                        Print / Save as PDF
                    </button>
                </div>

                <div className="flex items-center justify-center py-10 print:block print:py-0">
                    <div className="id-card-print relative flex h-[54mm] w-[86mm] flex-col overflow-hidden rounded-xl bg-white shadow-xl ring-1 ring-slate-200">
                        <div className="absolute inset-x-0 top-0 h-[14mm] bg-gradient-to-r from-teal-800 to-emerald-700" />

                        <div className="relative z-10 flex h-[14mm] items-center justify-between px-4">
                            <span className="truncate pr-2 text-[10pt] font-bold tracking-wide text-white">
                                {schoolName || 'Student Identification Card'}
                            </span>
                            {schoolLogo && <img src={schoolLogo} alt="" className="h-[10mm] max-w-[18mm] object-contain" />}
                        </div>

                        <div className="relative z-10 flex flex-1 px-4 pb-3 pt-2">
                            <div className="mr-3 shrink-0">
                                {photo ? (
                                    <img src={photo} alt="" className="h-[25mm] w-[20mm] rounded border border-slate-200 object-cover" />
                                ) : (
                                    <div className="flex h-[25mm] w-[20mm] items-center justify-center rounded border border-slate-200 bg-slate-50 text-[8pt] text-slate-400">
                                        No photo
                                    </div>
                                )}
                            </div>

                            <div className="min-w-0 flex-1 pt-1">
                                <h2 className="truncate text-[11pt] font-bold leading-tight text-slate-900">
                                    {student.name}
                                </h2>
                                <div className="mt-1 space-y-0.5 text-[8pt] text-slate-600">
                                    <p><span className="font-semibold text-slate-800">Adm:</span> {student.admissionNumber}</p>
                                    <p className="capitalize"><span className="font-semibold text-slate-800">Status:</span> {student.status}</p>
                                    {student.enrolment && (
                                        <p className="truncate">
                                            <span className="font-semibold text-slate-800">Class:</span>{' '}
                                            {student.enrolment.class} · {student.enrolment.section}
                                        </p>
                                    )}
                                </div>
                            </div>
                        </div>

                        {qrCode && (
                            <div
                                className="absolute bottom-3 right-3 h-[16mm] w-[16mm]"
                                dangerouslySetInnerHTML={{ __html: qrCode }}
                            />
                        )}
                    </div>
                </div>
            </AppLayout>
        </>
    );
}