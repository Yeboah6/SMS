import { Field, inputClass } from '../../Pages/Auth/Login';

export default function YearSelect({ years, value, onChange, allowAll = false, label = 'Academic year' }) {
    return (
        <Field label={label}>
            <select value={value ?? (allowAll ? 'all' : '')} onChange={(event) => onChange(event.target.value)} className={inputClass(false)}>
                {allowAll && <option value="all">All years</option>}
                {years.map((year) => <option key={year.id} value={year.id}>{year.name} · {year.status}</option>)}
            </select>
        </Field>
    );
}