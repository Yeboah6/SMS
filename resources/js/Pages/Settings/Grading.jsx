import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import PageAlerts from '../../Components/Exams/PageAlerts';
import SettingsNav from '../../Components/Settings/SettingsNav';
import SettingsSaveBar from '../../Components/Settings/SettingsSaveBar';
import { Field, inputClass } from '../Auth/Login';

export default function SettingsGrading({ settings }) {
    const form = useForm({
        attendance_threshold: settings.attendance_threshold,
        grade_settings: settings.grade_settings,
    });
    const bands = form.data.grade_settings.bands;

    function setBands(next) {
        form.setData('grade_settings', { bands: next });
    }

    function updateBand(index, field, value) {
        setBands(bands.map((band, position) => (position === index ? { ...band, [field]: value } : band)));
    }

    function moveBand(index, direction) {
        const next = [...bands];
        [next[index], next[index + direction]] = [next[index + direction], next[index]];
        setBands(next);
    }

    // Each band covers from its minimum up to just below the band above it.
    function rangeText(index) {
        const minimum = Number(bands[index].minimum_percentage);
        if (bands[index].minimum_percentage === '' || Number.isNaN(minimum)) return '';
        const above = index === 0 ? null : Number(bands[index - 1].minimum_percentage);
        if (above === null) return `${minimum}% and above`;
        return Number.isNaN(above) ? '' : `${minimum}% to below ${above}%`;
    }

    function submit(event) {
        event.preventDefault();
        form.put('/settings/grading');
    }

    return (
        <AppLayout title="Grading & attendance" description="How percentages become grades, and when attendance is flagged as low.">
            <Head title="Grading & attendance" />
            <PageAlerts />
            <SettingsNav current="grading" />

            <form onSubmit={submit} className="grid max-w-4xl gap-6">
                <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
                    <h2 className="font-semibold text-slate-900">Grade scale</h2>
                    <p className="mt-1 text-sm text-slate-500">Set the minimum percentage for each grade, ordered from highest to lowest. The final band must begin at 0%.</p>
                    <div className="mt-5 grid gap-3">
                        {bands.map((band, index) => (
                            <div key={`${index}-${band.label}`} className="grid gap-3 rounded-xl border border-slate-200 p-4 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] sm:items-end">
                                <Field label="Grade label" error={form.errors[`grade_settings.bands.${index}.label`]}>
                                    <input value={band.label} maxLength={10} onChange={(event) => updateBand(index, 'label', event.target.value)} className={inputClass(form.errors[`grade_settings.bands.${index}.label`])} required />
                                </Field>
                                <div>
                                    <Field label="Minimum percentage" error={form.errors[`grade_settings.bands.${index}.minimum_percentage`]}>
                                        <input type="number" min="0" max="100" step="0.01" value={band.minimum_percentage} onChange={(event) => updateBand(index, 'minimum_percentage', event.target.value)} className={inputClass(form.errors[`grade_settings.bands.${index}.minimum_percentage`])} required />
                                    </Field>
                                    <p className="mt-1 text-xs text-slate-500">{rangeText(index)}</p>
                                </div>
                                <div className="flex gap-2">
                                    <button type="button" disabled={index === 0} onClick={() => moveBand(index, -1)} className="rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 disabled:opacity-40" aria-label={`Move grade ${band.label} up`}>Up</button>
                                    <button type="button" disabled={index === bands.length - 1} onClick={() => moveBand(index, 1)} className="rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 disabled:opacity-40" aria-label={`Move grade ${band.label} down`}>Down</button>
                                    <button type="button" disabled={bands.length <= 2} onClick={() => setBands(bands.filter((_, position) => position !== index))} className="rounded-lg border border-rose-200 px-3 py-2 text-sm font-medium text-rose-700 disabled:opacity-40" aria-label={`Remove grade ${band.label}`}>Remove</button>
                                </div>
                            </div>
                        ))}
                        {form.errors['grade_settings.bands'] && <p role="alert" className="text-sm text-rose-700">{form.errors['grade_settings.bands']}</p>}
                        <button type="button" disabled={bands.length >= 12} onClick={() => setBands([...bands, { label: '', minimum_percentage: '' }])} className="w-fit rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50">Add grade band</button>
                    </div>
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
                    <h2 className="font-semibold text-slate-900">Attendance</h2>
                    <div className="mt-5 max-w-sm">
                        <Field label="Low-attendance threshold (%)" error={form.errors.attendance_threshold}>
                            <input type="number" min="0" max="100" step="0.01" value={form.data.attendance_threshold} onChange={(event) => form.setData('attendance_threshold', event.target.value)} className={inputClass(form.errors.attendance_threshold)} required />
                        </Field>
                        <p className="mt-1.5 text-xs text-slate-500">Students whose attendance falls below this percentage are flagged in attendance reports.</p>
                    </div>
                </section>

                <SettingsSaveBar form={form} label="Save grading settings" />
            </form>
        </AppLayout>
    );
}