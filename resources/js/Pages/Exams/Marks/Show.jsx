import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import ResultCard from '../../Components/Exams/ResultCard';

export default function ResultsShow({ mode, studentId, studentName, result }) {
    const back = studentId ? `/results?student_id=${studentId}` : '/results';

    return (
        <AppLayout title={result.name} description={mode === 'parent' ? `Published result for ${studentName}` : 'Your published result'}>
            <Head title={result.name} />
            <div className="mb-6">
                <Link href={back} className="text-sm font-semibold text-teal-800 hover:text-teal-950">Back to results</Link>
            </div>
            <ResultCard result={result} />
        </AppLayout>
    );
}