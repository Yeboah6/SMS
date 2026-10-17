import { useForm } from '@inertiajs/react';
import { Field, inputClass } from '../../Pages/Auth/Login';
import { humanize } from '../../lib/admissions';

export default function AdmissionDocuments({ admission }) {
    const form = useForm({ category: 'application', document: null });

    function upload(event) {
        event.preventDefault();
        form.post(`/admissions/${admission.id}/documents`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => form.reset('document'),
        });
    }

    return (
        <section>
            <h3 className="text-sm font-semibold text-slate-800">Admission documents</h3>
            <ul className="mt-2 space-y-2">
                {admission.documents.map((document) => (
                    <li key={document.id} className="flex flex-wrap justify-between gap-2 text-sm">
                        <span className="text-slate-600">{document.name} · {humanize(document.category)}</span>
                        <a className="font-medium text-teal-800 hover:text-teal-950" href={`/admissions/${admission.id}/documents/${document.id}`}>Download</a>
                    </li>
                ))}
                {admission.documents.length === 0 && <li className="text-sm text-slate-500">No documents uploaded.</li>}
            </ul>
            <form onSubmit={upload} className="mt-3 flex flex-col gap-2 sm:flex-row sm:items-end">
                <Field label="Document type" error={form.errors.category}>
                    <select value={form.data.category} onChange={(event) => form.setData('category', event.target.value)} className={inputClass(form.errors.category)}>
                        {['application', 'birth_certificate', 'identity', 'medical', 'transfer_certificate', 'other'].map((category) => <option key={category} value={category}>{humanize(category)}</option>)}
                    </select>
                </Field>
                <Field label="PDF or image (max 10 MB)" error={form.errors.document}>
                    <input type="file" accept=".pdf,.jpg,.jpeg,.png" onChange={(event) => form.setData('document', event.target.files[0])} className={inputClass(form.errors.document)} />
                </Field>
                <button disabled={form.processing || !form.data.document} className="rounded-xl bg-teal-800 px-4 py-3 text-sm font-semibold text-white disabled:opacity-50">Upload</button>
            </form>
        </section>
    );
}