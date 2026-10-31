import { Link } from "@inertiajs/react";
import { Field, inputClass } from "../../Pages/Auth/Login";

export default function RequirementForm({
    form,
    onSubmit,
    classes = null,
    terms,
    items,
    fixed = null,
    cancelHref,
    submitLabel,
}) {
    const set = (key) => (event) => form.setData(key, event.target.value);

    return (
        <form onSubmit={onSubmit} className="grid gap-4 sm:grid-cols-2">
            {fixed ? (
                <div className="sm:col-span-2">
                    <p className="text-sm font-medium text-slate-700">Class</p>
                    <p className="mt-1 text-sm text-slate-900">{fixed}</p>
                </div>
            ) : (
                <Field label="Class" error={form.errors.class_id}>
                    <select
                        value={form.data.class_id}
                        onChange={set("class_id")}
                        className={inputClass(form.errors.class_id)}
                        required
                    >
                        <option value="">Select class</option>
                        {classes.map((item) => (
                            <option key={item.id} value={item.id}>
                                {item.name}
                            </option>
                        ))}
                    </select>
                </Field>
            )}
            <Field label="Item" error={form.errors.item_id}>
                <select
                    value={form.data.item_id}
                    onChange={set("item_id")}
                    className={inputClass(form.errors.item_id)}
                    required
                >
                    <option value="">Select item</option>
                    {items.map((item) => (
                        <option key={item.id} value={item.id}>
                            {item.name} ({item.sku})
                        </option>
                    ))}
                </select>
            </Field>
            <Field
                label="Quantity per student"
                error={form.errors.quantity_per_student}
            >
                <input
                    type="number"
                    min="1"
                    max="1000"
                    value={form.data.quantity_per_student}
                    onChange={set("quantity_per_student")}
                    className={inputClass(form.errors.quantity_per_student)}
                    required
                />
            </Field>
            <Field label="Term (optional)" error={form.errors.term_id}>
                <select
                    value={form.data.term_id}
                    onChange={set("term_id")}
                    className={inputClass(form.errors.term_id)}
                >
                    <option value="">Whole year</option>
                    {terms.map((term) => (
                        <option key={term.id} value={term.id}>
                            {term.name}
                        </option>
                    ))}
                </select>
            </Field>
            <label className="flex items-start gap-3 text-sm text-slate-700 sm:col-span-2">
                <input
                    type="checkbox"
                    checked={form.data.is_required}
                    onChange={(event) =>
                        form.setData("is_required", event.target.checked)
                    }
                    className="mt-0.5 size-4 rounded border-slate-300 text-teal-700 focus:ring-teal-700"
                />
                <span>
                    Required
                    <span className="block text-xs text-slate-500">
                        Untick for a recommended item. Only required items count
                        towards a student being complete.
                    </span>
                </span>
            </label>
            <div className="sm:col-span-2">
                <Field label="Notes" error={form.errors.notes}>
                    <input
                        value={form.data.notes}
                        onChange={set("notes")}
                        className={inputClass(form.errors.notes)}
                        maxLength={1000}
                    />
                </Field>
            </div>
            <p className="text-xs text-slate-500 sm:col-span-2">
                Students are not required to buy from the school. Anything
                bought here counts automatically; staff can mark an item as
                bought elsewhere.
            </p>
            <div className="flex flex-wrap gap-3 sm:col-span-2">
                <button
                    disabled={form.processing}
                    className="rounded-xl bg-teal-800 px-5 py-3 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-60"
                >
                    {form.processing ? "Saving…" : submitLabel}
                </button>
                <Link
                    href={cancelHref}
                    className="rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                >
                    Cancel
                </Link>
            </div>
        </form>
    );
}
