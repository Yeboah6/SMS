import { DOCUMENT_TITLES } from '../../lib/certificates';

/** The printable certificate. A revoked certificate is stamped VOID so a printout cannot pass as valid. */
export default function CertificateDocument({ certificate }) {
    return (
        <article className="relative mx-auto max-w-4xl overflow-hidden border-8 border-double border-teal-800 bg-white p-8 text-center shadow-sm sm:p-14 print:max-w-none print:border-8 print:p-16 print:shadow-none">
            {certificate.revoked && (
                <div aria-hidden="true" className="pointer-events-none absolute inset-0 flex items-center justify-center">
                    <span className="-rotate-12 select-none rounded-xl border-8 border-rose-600/40 px-10 py-4 text-7xl font-black tracking-widest text-rose-600/40 sm:text-8xl">VOID</span>
                </div>
            )}
            <p className="text-sm font-semibold uppercase tracking-[0.25em] text-teal-800">Official school document</p>
            <h1 className="mt-5 text-3xl font-bold tracking-tight text-slate-950 sm:text-4xl">{certificate.schoolName}</h1>
            <div className="mx-auto my-8 h-px max-w-xl bg-teal-200" />
            <h2 className="text-2xl font-semibold text-slate-800 sm:text-3xl">
                {DOCUMENT_TITLES[certificate.type] || `${certificate.type} Certificate`}
            </h2>
            <p className="mt-10 text-lg leading-8 text-slate-700">This certificate confirms the school record for</p>
            <p className="mt-4 text-3xl font-bold text-slate-950 sm:text-4xl">{certificate.studentName}</p>
            <p className="mt-4 text-base text-slate-600">Admission number: {certificate.admissionNumber}</p>
            <p className="mx-auto mt-8 max-w-2xl text-base leading-7 text-slate-700">
                This document was reviewed and issued by the school on {new Date(`${certificate.issuedAt}T00:00:00`).toLocaleDateString()}.
            </p>
            <div className="mt-16 flex flex-col justify-between gap-12 text-left sm:flex-row sm:items-end">
                <div className="min-w-52 border-t border-slate-400 pt-2 text-sm text-slate-600">Authorized signature</div>
                <div className="text-right text-sm text-slate-500">
                    <p className="font-semibold text-slate-700">Certificate number</p>
                    <p className="mt-1">{certificate.number}</p>
                </div>
            </div>
        </article>
    );
}