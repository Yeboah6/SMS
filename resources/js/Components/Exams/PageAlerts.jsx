import { createContext, useCallback, useContext, useEffect, useId, useMemo, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { CircleSlash, Info, TriangleAlert, X, Check } from 'lucide-react';

const PageAlertsContext = createContext(null);
const alertStyles = {
    info: {
        Icon: Info,
        className: 'border-sky-300 bg-sky-100 text-sky-800',
    },
    warning: {
        Icon: TriangleAlert,
        className: 'border-amber-300 bg-amber-100 text-amber-900',
    },
    error: {
        Icon: CircleSlash,
        className: 'border-rose-300 bg-rose-100 text-rose-800',
    },
    success: {
        Icon: Check,
        className: 'border-emerald-300 bg-emerald-100 text-emerald-900',
    },
};

export function PageAlertsProvider({ children }) {
    const [formErrors, setFormErrors] = useState({});
    const registerErrors = useCallback((id, errors) => {
        setFormErrors((current) => {
            if (!errors || Object.keys(errors).length === 0) {
                if (!(id in current)) {
                    return current;
                }

                const next = { ...current };
                delete next[id];

                return next;
            }

            if (JSON.stringify(current[id]) === JSON.stringify(errors)) {
                return current;
            }

            return { ...current, [id]: errors };
        });
    }, []);
    const contextValue = useMemo(() => ({ registerErrors }), [registerErrors]);

    return (
        <PageAlertsContext.Provider value={contextValue}>
            <PageAlertToast additionalErrors={Object.values(formErrors)} />
            {children}
        </PageAlertsContext.Provider>
    );
}

/** Flash status plus any server-side errors (including service-level ones). */
export default function PageAlerts({ errors: formErrors }) {
    const context = useContext(PageAlertsContext);
    const id = useId();
    const errorsKey = JSON.stringify(formErrors ?? {});
    const normalizedErrors = useMemo(() => JSON.parse(errorsKey), [errorsKey]);

    useEffect(() => {
        if (!context) {
            return undefined;
        }

        context.registerErrors(id, normalizedErrors);

        return () => context.registerErrors(id, {});
    }, [context, errorsKey, id, normalizedErrors]);

    if (context) {
        return null;
    }

    return <PageAlertToast additionalErrors={formErrors ? [formErrors] : []} />;
}

function PageAlertToast({ additionalErrors = [] }) {
    const { status, info, warning, success, errors = {} } = usePage().props;
    const messages = [...new Set([
        ...Object.values(errors).flat(),
        ...additionalErrors.flatMap((formError) => Object.values(formError).flat()),
    ].filter(Boolean))];
    const alerts = [
        ...(info ? [{ type: 'info', label: 'Info', message: info }] : []),
        ...(warning ? [{ type: 'warning', label: 'Warning', message: warning }] : []),
        ...(messages.length > 0 ? [{ type: 'error', label: 'Error', messages }] : []),
        ...((success || status) ? [{ type: 'success', label: 'Success', message: success || status }] : []),
    ];
    const alertKey = JSON.stringify(alerts);
    const hasAlerts = alerts.length > 0;
    const [visible, setVisible] = useState(false);
    const [dismissed, setDismissed] = useState(false);
    const [dismissedAlerts, setDismissedAlerts] = useState([]);

    useEffect(() => {
        if (!hasAlerts) {
            return undefined;
        }

        setVisible(false);
        setDismissed(false);
        setDismissedAlerts([]);

        const frame = requestAnimationFrame(() => setVisible(true));
        const hideTimer = window.setTimeout(() => setVisible(false), 5000);
        const dismissTimer = window.setTimeout(() => setDismissed(true), 5300);

        return () => {
            cancelAnimationFrame(frame);
            window.clearTimeout(hideTimer);
            window.clearTimeout(dismissTimer);
        };
    }, [alertKey, hasAlerts]);

    if (!hasAlerts || dismissed || dismissedAlerts.length === alerts.length) {
        return null;
    }

    return (
        <div className="pointer-events-none fixed right-4 top-4 z-[100] flex w-[min(24rem,calc(100vw-2rem))] flex-col gap-3 sm:right-6 sm:top-6">
            {alerts.map((alert) => {
                if (dismissedAlerts.includes(alert.type)) {
                    return null;
                }

                const { Icon, className } = alertStyles[alert.type];
                const role = alert.type === 'error' ? 'alert' : 'status';

                return (
                    <div
                        key={alert.type}
                        role={role}
                        className={`pointer-events-auto flex items-center gap-3 rounded-2xl border px-4 py-3.5 text-sm shadow-lg shadow-slate-900/10 transition duration-300 ease-out ${
                            visible ? 'translate-x-0 opacity-100' : 'translate-x-full opacity-0'
                        } ${className}`}
                    >
                        <Icon size={22} strokeWidth={2.5} className="shrink-0" aria-hidden="true" />
                        <div className="min-w-0 flex-1">
                            <p className="font-semibold">{alert.label}: <span className="font-normal">{alert.message || alert.messages?.join(' ')}</span></p>
                        </div>
                        <button
                            type="button"
                            aria-label={`Dismiss ${alert.label.toLowerCase()} alert`}
                            onClick={() => setDismissedAlerts((current) => [...current, alert.type])}
                            className="shrink-0 rounded-lg p-1 opacity-60 transition hover:bg-black/5 hover:opacity-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-current"
                        >
                            <X size={16} aria-hidden="true" />
                        </button>
                    </div>
                );
            })}
        </div>
    );
}