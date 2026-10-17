import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import ResultCard from '../../Components/Exams/ResultCard';
import { Field, inputClass } from '../Auth/Login';

export default function ResultsIndex({ mode, results, academicYears, selectedAcademicYearId, students, selectedStudentId }) {
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

            {mode === 'parent' && !selectedStudentId ? (
                <Empty
                    title={students.length === 0 ? 'No linked children' : 'Select a child'}
                    detail={students.length === 0 ? 'Contact the school to link your parent account to a student.' : 'Choose one of your linked children to view their published results.'}
                />
            ) : results.data.length === 0 ? (
                <Empty title="No published results found" detail="Results will appear here after the school publishes an examination." />
            ) : (
                <div className="grid gap-5">
                    {results.data.map((result) => <ResultCard key={result.id} result={result} detailHref={detailHref(result)} />)}
                </div>
            )}

            {results.links.length > 3 && (
                <nav aria-label="Results pages" className="mt-6 flex flex-wrap gap-2">
                    {results.links.map((link, index) => (
                        <Link
                            key={`${link.label}-${index}`}
                            href={link.url || '#'}
                            preserveScroll
                            className={`rounded-lg px-3 py-2 text-sm ${link.active ? 'bg-teal-700 text-white' : 'border border-slate-200 bg-white text-slate-700'} ${!link.url ? 'pointer-events-none opacity-50' : ''}`}
                        >
                            {link.label.replace('&laquo;', '‹').replace('&raquo;', '›')}
                        </Link>
                    ))}
                </nav>
            )}
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