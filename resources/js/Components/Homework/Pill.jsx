export default function Pill({ map, value }) {
    const [label, tone] = map[value] || [String(value), 'bg-slate-100 text-slate-700'];

    return <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${tone}`}>{label}</span>;
}