import { Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import EmptyState from './EmptyState';

export default function StudentsTable({ students, showSection = false }) {
    const [query, setQuery] = useState('');

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) return students;
        return students.filter((student) =>
            student.name.toLowerCase().includes(q) || String(student.studentNumber ?? '').toLowerCase().includes(q),
        );
    }, [students, query]);

    if (students.length === 0) {
        return <EmptyState title="No students yet" detail="Students enrolled in this section will appear here." />;
    }

    return (
        <div>
            <input
                type="search"
                value={query}
                onChange={(event) => setQuery(event.target.value)}
                placeholder="Search by name or student number"
                aria-label="Search students"
                className="mb-3 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm sm:max-w-sm"
            />
            <div className="overflow-x-auto rounded-xl border border-slate-100">
                <table className="min-w-full divide-y divide-slate-100 text-sm">
                    <thead className="bg-slate-50 text-left text-xs font-semibold text-slate-600">
                        <tr>
                            <th scope="col" className="px-3 py-2.5">Student</th>
                            <th scope="col" className="px-3 py-2.5">Number</th>
                            <th scope="col" className="px-3 py-2.5">Gender</th>
                            {showSection && <th scope="col" className="px-3 py-2.5">Section</th>}
                            <th scope="col" className="px-3 py-2.5">Status</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100 bg-white text-slate-700">
                        {filtered.map((student) => (
                            <tr key={student.id}>
                                <td className="px-3 py-2.5 font-medium text-slate-900">{student.name}</td>
                                <td className="px-3 py-2.5">{student.studentNumber ?? '—'}</td>
                                <td className="px-3 py-2.5 capitalize">{student.gender ?? '—'}</td>
                                {showSection && (
                                    <td className="px-3 py-2.5">
                                        <Link href={`/sections/${student.sectionId}`} className="font-semibold text-teal-800 hover:text-teal-950">{student.sectionName}</Link>
                                    </td>
                                )}
                                <td className="px-3 py-2.5 capitalize">{student.status ?? '—'}</td>
                            </tr>
                        ))}
                        {filtered.length === 0 && (
                            <tr><td colSpan={showSection ? 5 : 4} className="px-3 py-6 text-center text-slate-500">No students match “{query}”.</td></tr>
                        )}
                    </tbody>
                </table>
            </div>
            <p className="mt-2 text-xs text-slate-500">{filtered.length} of {students.length} students</p>
        </div>
    );
}