import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import ResultCard from '../../Components/Exams/ResultCard';
import Pagination from '../../Components/Pagination';
import { Field, inputClass } from '../Auth/Login';

export default function ResultsIndex({ mode, results, academicYears, selectedAcademicYearId, students, selectedStudentId, bookRequirements }) {
    const title = mode === 'parent' ? 'Children’s results' : 'My results';

    function updateFilters(studentId, academicYearId) {
        router.get('/results', {
            ...(studentId ? { student_id: studentId } : {}),
            ...(academicYearId ? { academic_year_id: academicYearId } : {}),
        }, { preserveScroll: true, replace: true });
    }

    function detailHref(result) {
        return `/results/${result.id}${selectedStudentId ? `?student_id=${selectedStudentId}` : ''}`;
    }

    function reportCardHref(result) {
        return `/results/${result.id}/report-card${selectedStudentId ? `?student_id=${selectedStudentId}` : ''}`;
    }
    
    function downloadHref(result) {
        return `/results/${result.id}/report-card/download${selectedStudentId ? `?student_id=${selectedStudentId}` : ''}`;
    }

    return (
        <AppLayout title={title} description={mode === 'parent' ? 'Review your linked children’s published results.' : 'Review your published examination results.'}>
            <Head title={title} />

            <section className="mb-6 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h2 className="font-semibold text-slate-900">{mode === 'parent' ? 'Children’s published results' : 'Published results'}</h2>
                        <p className="mt-1 text-sm text-slate-500">Only officially published examination results appear here.</p>
                    </div>
                    <div className="flex flex-wrap gap-3">
                        {mode === 'parent' && (
                            <Field label="Child">
                                <select value={selectedStudentId || ''} onChange={(event) => updateFilters(event.target.value, selectedAcademicYearId)} className={inputClass(false)}>
                                    <option value="">Select a child</option>
                                    {students.map((student) => <option key={student.id} value={student.id}>{student.name}</option>)}
                                </select>
                            </Field>
                        )}
                        <Field label="Academic year">
                            <select value={selectedAcademicYearId || ''} onChange={(event) => updateFilters(selectedStudentId, event.target.value)} className={inputClass(false)}>
                                <option value="">All years</option>
                                {academicYears.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                            </select>
                        </Field>
                    </div>
                </div>
            </section>

            {mode === 'parent' && selectedStudentId && (
                <section className="mb-6 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h2 className="font-semibold text-slate-900">Book requirements</h2>
                            {bookRequirements ? (
                                <p className="mt-1 text-sm text-slate-500">
                                    {bookRequirements.className} · {bookRequirements.academicYear}
                                </p>
                            ) : (
                                <p className="mt-1 text-sm text-slate-500">No current class requirements are available for this student.</p>
                            )}
                        </div>
                        {bookRequirements && (
                            <div className="flex flex-wrap items-center gap-3">
                                <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${bookRequirements.complete ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-800'}`}>
                                    {bookRequirements.complete ? 'All required items covered' : 'Items still needed'}
                                </span>
                                <a
                                    href={`/results/students/${selectedStudentId}/book-requirements/download`}
                                    className="rounded-lg bg-teal-800 px-3 py-2 text-sm font-semibold text-white hover:bg-teal-900"
                                >
                                    Download requirements
                                </a>
                            </div>
                        )}
                    </div>
                    {bookRequirements && (bookRequirements.items.data.length === 0 ? (
                        <p className="mt-4 rounded-xl bg-slate-50 px-3 py-4 text-sm text-slate-500">No book requirements are set for this class.</p>
                    ) : (
                        <div className="mt-4 overflow-x-auto">
                            <table className="w-full min-w-[560px] text-left text-sm">
                                <thead className="border-b border-slate-100 text-xs text-slate-500">
                                    <tr>
                                        <th className="py-3 pr-4">Book / item</th>
                                        <th className="py-3 pr-4">Quantity</th>
                                        <th className="py-3 pr-4">Bought</th>
                                        <th className="py-3 pr-4">Still needed</th>
                                        <th className="py-3">Term</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {bookRequirements.items.data.map((item) => (
                                        <tr key={item.id}>
                                            <td className="py-3 pr-4 text-slate-800">
                                                {item.itemName}
                                                {!item.isRequired && <span className="ml-2 text-xs text-slate-500">recommended</span>}
                                                {item.notes && <span className="block text-xs text-slate-500">{item.notes}</span>}
                                            </td>
                                            <td className="py-3 pr-4">{item.quantity} {item.unit}</td>
                                            <td className="py-3 pr-4">{item.purchased}</td>
                                            <td className="py-3 pr-4">{item.outstanding}</td>
                                            <td className="py-3">{item.termName || 'Full year'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ))}
                    {bookRequirements && (
                        <Pagination links={bookRequirements.items.links} from={bookRequirements.items.from} to={bookRequirements.items.to} total={bookRequirements.items.total} />
                    )}
                </section>
            )}

            {mode === 'parent' && !selectedStudentId ? (
                <Empty
                    title={students.length === 0 ? 'No linked children' : 'Select a child'}
                    detail={students.length === 0 ? 'Contact the school to link your parent account to a student.' : 'Choose one of your linked children to view their published results.'}
                />
            ) : results.data.length === 0 ? (
                <Empty title="No published results found" detail="Results will appear here after the school publishes an examination." />
            ) : (
                <div className="grid gap-5">
                    {results.data.map((result) => (
                        <div key={result.id}>
                            <ResultCard result={result} detailHref={detailHref(result)} />
                            <div className="mt-2 flex flex-wrap items-center justify-end gap-3 text-sm font-semibold">
                                <Link href={detailHref(result)} className="text-teal-800 hover:text-teal-950">Details</Link>
                                <Link href={reportCardHref(result)} className="text-teal-800 hover:text-teal-950">Report card</Link>
                                <a href={downloadHref(result)} className="rounded-lg bg-teal-700 px-3 py-2 text-white hover:bg-teal-800">Download PDF</a>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            <Pagination links={results.links} from={results.from} to={results.to} total={results.total} />
        </AppLayout>
    );
}

function Empty({ title, detail }) {
    return (
        <section className="rounded-2xl border border-slate-200 bg-white px-6 py-14 text-center">
            <p className="font-medium text-slate-800">{title}</p>
            <p className="mt-1 text-sm text-slate-500">{detail}</p>
        </section>
    );
}