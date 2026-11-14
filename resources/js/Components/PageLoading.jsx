import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function PageLoading() {
    const [isVisible, setIsVisible] = useState(false);

    useEffect(() => {
        let showTimeout;

        const stopListeningForStart = router.on('start', () => {
            window.clearTimeout(showTimeout);
            showTimeout = window.setTimeout(() => setIsVisible(true), 120);
        });
        const stopListeningForFinish = router.on('finish', () => {
            window.clearTimeout(showTimeout);
            setIsVisible(false);
        });

        return () => {
            window.clearTimeout(showTimeout);
            stopListeningForStart();
            stopListeningForFinish();
        };
    }, []);

    if (!isVisible) {
        return null;
    }

    return (
        <div className="fixed inset-0 z-[100] flex items-center justify-center bg-white/75 px-4 backdrop-blur-sm" role="status" aria-live="polite">
            <div className="flex min-w-56 flex-col items-center gap-4 rounded-2xl border border-slate-200 bg-white px-8 py-7 shadow-xl shadow-slate-900/10">
                <span className="size-10 animate-spin rounded-full border-4 border-teal-100 border-t-teal-700 motion-reduce:animate-none" aria-hidden="true" />
                <span className="text-sm font-semibold text-slate-700">Loading page...</span>
            </div>
        </div>
    );
}
