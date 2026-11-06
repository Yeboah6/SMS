import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import ResultCard from '../../Components/Exams/ResultCard';

export default function ResultsShow({ mode, studentId, studentName, result }) {
    const back = studentId ? `/results?student_id=${studentId}` : '/results';
    const query = studentId ? `?student_id=${studentId}` : '';

    return (
        <AppLayout title={result.name} description={mode === 'parent' ? `Published result for ${studentName}` : 'Your published result'}>
            <Head title={result.name} />
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <Link href={back} className="text-sm font-semibold text-teal-800 hover:text-teal-950">Back to results</Link>
                <div className="flex items-center gap-3 text-sm font-semibold">
                    <Link href={`/results/${result.id}/report-card${query}`} className="text-teal-800 hover:text-teal-950">View report card</Link>
                    <a href={`/results/${result.id}/report-card/download${query}`} className="rounded-lg bg-teal-700 px-3 py-2 text-white hover:bg-teal-800">Download report card</a>
                </div>
            </div>
            <ResultCard result={result} />
        </AppLayout>
    );
}