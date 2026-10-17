import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import LibraryNav from '../../../Components/Books/LibraryNav';
import BookForm from '../../../Components/Books/BookForm';

export default function BooksEdit({ book }) {
    const form = useForm({
        title: book.title,
        author: book.author,
        isbn: book.isbn || '',
        category: book.category,
        publisher: book.publisher || '',
        description: book.description || '',
        is_active: book.isActive,
    });

    function submit(event) {
        event.preventDefault();
        form.put(`/library/books/${book.id}`);
    }

    return (
        <AppLayout title={`Edit ${book.title}`} description="Update the title details. Copies are managed on the book page.">
            <Head title={`Edit ${book.title}`} />
            <LibraryNav current="inventory" />
            <section className="max-w-3xl rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <BookForm form={form} onSubmit={submit} isEditing cancelHref={`/library/books/${book.id}`} />
            </section>
        </AppLayout>
    );
}