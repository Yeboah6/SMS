import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { Field, inputClass } from '../Auth/Login';

export default function StudentsImport({ columns, report }) {
    const form = useForm({ file: null });

    function submit(event) {
        event.preventDefault();
        form.post('/students/import', { forceFormData: true, preserveScroll: true });
    }

    return (
        <AppLayout title="Import students" description="Add many existing students at once from a CSV file.">
            <Head title="Import students" />

            <div className="mb-6">
                <Link href="/students" className="text-sm font-semibold text-teal-800 hover:text-teal-950">Back to students</Link>
            </div>

            <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <h2 className="font-semibold text-slate-900">Upload a CSV file</h2>
                <ul className="mt-3 list-disc space-y-1 pl-5 text-sm text-slate-600">
                    <li>Each row is one student with one guardian. Add further guardians afterwards on the student profile.</li>
                    <li>Enter an existing <span className="font-medium">admission_number</span> for students admitted before this system. Leave it blank to generate the next number.</li>
                    <li>Dates use the format YYYY-MM-DD. Gender is female, male, other or prefer_not_to_say.</li>
                    <li>Every row is checked first. If any row has a problem, nothing is imported. Up to 500 students per file.</li>
                </ul>
                <p className="mt-3 text-sm">
                    <a href="/students/import/template" className="font-semibold text-teal-800 hover:text-teal-950">Download the CSV template</a>
                </p>

                <form onSubmit={submit} className="mt-5 grid gap-3 sm:grid-cols-[1fr_auto] sm:items-end">
                    <Field label="CSV file (up to 2 MB)" error={form.errors.file}>
                        <input type="file" accept=".csv,text/csv,text/plain" onChange={(event) => form.setData('file', event.target.files?.[0] || null)} className={inputClass(form.errors.file)} required />
                    </Field>
                    <button disabled={form.processing || !form.data.file} className="rounded-xl bg-teal-800 px-5 py-3 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-60">
                        {form.processing ? 'Importing…' : 'Import students'}
                    </button>
                </form>
            </section>

            {report && report.errors.length > 0 && (
                <section role="alert" className="mt-6 rounded-2xl border border-rose-200 bg-rose-50 p-5 sm:p-6">
                    <h2 className="font-semibold text-rose-900">Nothing was imported</h2>
                    <p className="mt-1 text-sm text-rose-800">
                        {report.errors.length} {report.errors.length === 1 ? 'row has' : 'rows have'} problems. Fix them in the file and upload it again.
                    </p>
                    <ul className="mt-4 space-y-3 text-sm text-rose-900">
                        {report.errors.map((error) => (
                            <li key={error.line}>
                                <p className="font-medium">Line {error.line}</p>
                                <ul className="mt-1 list-disc pl-5">
                                    {error.messages.map((message) => <li key={message}>{message}</li>)}
                                </ul>
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            <section className="mt-6 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <h2 className="font-semibold text-slate-900">Columns</h2>
                <p className="mt-1 text-sm text-slate-500">Required: first_name, last_name, date_of_birth, gender, admission_date, guardian_name, guardian_phone. Status defaults to active and guardian_relationship to Parent.</p>
                <p className="mt-3 break-words font-mono text-xs leading-6 text-slate-600">{columns.join(', ')}</p>
            </section>
        </AppLayout>
    );
}