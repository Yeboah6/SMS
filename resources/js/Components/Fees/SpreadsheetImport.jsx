import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Field, inputClass } from '../../Pages/Auth/Login';

export default function SpreadsheetImport({ action, label, description }) {
    const [inputKey, setInputKey] = useState(0);
    const form = useForm({ file: null });

    function submit(event) {
        event.preventDefault();
        form.post(action, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setInputKey((key) => key + 1);
            },
        });
    }

    return (
        <div className="rounded-xl border border-slate-200 p-4">
            <p className="mb-3 text-sm text-slate-500">{description}</p>
            <form onSubmit={submit} className="flex flex-wrap items-end gap-3">
                <Field label={label} error={form.errors.file}>
                    <input key={inputKey} type="file" accept=".xlsx,.xls,.csv" onChange={(event) => form.setData('file', event.target.files?.[0] ?? null)} className={inputClass(form.errors.file)} required />
                </Field>
                <button type="submit" disabled={form.processing} className="rounded-lg border border-teal-700 px-3 py-2 text-sm font-semibold text-teal-800 hover:bg-teal-50 disabled:opacity-50">
                    {form.processing ? 'Importing…' : 'Import spreadsheet'}
                </button>
            </form>
        </div>
    );
}