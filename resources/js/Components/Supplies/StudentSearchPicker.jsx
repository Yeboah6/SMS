import { useEffect, useState } from 'react';
import { Field, inputClass } from '../../Pages/Auth/Login';

/** Search by admission number, name, class or student ID. Searches the server so large schools stay fast. */
export default function StudentSearchPicker({ value, onChange, error, label = 'Student' }) {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState([]);

    useEffect(() => {
        if (value || query.trim().length < 2) {
            setResults([]);
            return undefined;
        }

        const controller = new AbortController();
        const timer = setTimeout(async () => {
            try {
                const response = await fetch(`/supplies/students/search?q=${encodeURIComponent(query.trim())}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: controller.signal });
                if (response.ok) setResults(await response.json());
            } catch {
                // aborted or offline: keep what is shown
            }
        }, 250);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [query, value]);

    return (
        <Field label={label} error={error}>
            {value ? (
                <div className="flex items-center justify-between gap-2 rounded-xl border border-teal-200 bg-teal-50 px-3 py-2.5 text-sm">
                    <span className="text-teal-900">{value.name} · {value.admissionNumber}{value.className ? ` · ${value.className}` : ''}</span>
                    <button type="button" onClick={() => { onChange(null); setQuery(''); }} className="text-xs font-semibold text-teal-800 hover:text-teal-950">Change</button>
                </div>
            ) : (
                <div className="relative">
                    <input value={query} onChange={(event) => setQuery(event.target.value)} className={inputClass(error)} placeholder="Admission no., name, class or student ID" autoComplete="off" />
                    {results.length > 0 && (
                        <ul className="absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-xl border border-slate-200 bg-white py-1 shadow-lg">
                            {results.map((student) => (
                                <li key={student.id}>
                                    <button type="button" onClick={() => { onChange(student); setResults([]); }} className="block w-full px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50">
                                        {student.name} <span className="text-xs text-slate-500">· {student.admissionNumber}{student.className ? ` · ${student.className}` : ''}</span>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            )}
        </Field>
    );
}