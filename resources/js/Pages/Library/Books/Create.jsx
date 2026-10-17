import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import LibraryNav from '../../../Components/Books/LibraryNav';
import BookForm from '../../../Components/Books/BookForm';

export default function BooksCreate() {
    const form = useForm({ title: '', author: '', isbn: '', category: '', publisher: '', description: '', copies: 1, shelf_location: '' });

    function submit(event) {
        event.preventDefault();
        form.post('/library/books');
    }

    return (
        <AppLayout title="Add book" description="Create a title and register its physical copies, each with a unique accession number.">
            <Head title="Add book" />
            <LibraryNav current="inventory" />
            <section className="max-w-3xl rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <BookForm form={form} onSubmit={submit} cancelHref="/library/books" />
            </section>
        </AppLayout>
    );
}