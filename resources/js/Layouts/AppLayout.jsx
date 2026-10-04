import { Link, router, usePage } from '@inertiajs/react';
import {
    Bell,
    BookOpen,
    ChevronDown,
    ClipboardList,
    GraduationCap,
    LayoutDashboard,
    LogOut,
    Menu,
    CalendarDays,
    Search,
    UsersRound,
    ShieldCheck,
    SlidersHorizontal,
    X,
} from 'lucide-react';
import { useState } from 'react';

const navigation = [
    { label: 'Overview', href: '/dashboard', icon: LayoutDashboard },
    { label: 'Academic years', href: '/academic-years', icon: CalendarDays, permission: 'academic_years.view' },
    { label: 'Classes & sections', href: '/classes', icon: ClipboardList, permission: 'classes.view' },
    { label: 'Subjects', href: '/subjects', icon: BookOpen, permission: 'subjects.view' },
    { label: 'Staff', href: '/staff', icon: UsersRound, permission: 'staff.view' },
    { label: 'Students', href: '/students', icon: GraduationCap, permission: 'students.view' },
    { label: 'School settings', href: '/settings', icon: SlidersHorizontal, permission: 'settings.view' },
    { label: 'Users', href: '/users', icon: GraduationCap, permission: 'users.view' },
    { label: 'Roles', href: '/roles', icon: BookOpen, permission: 'roles.view' },
    { label: 'Permissions', href: '/permissions', icon: ShieldCheck, permission: 'permissions.view' },
];

export default function AppLayout({ children, title, description }) {
    const { appName, auth, activeAcademicYear, schoolSettings } = usePage().props;
    const [menuOpen, setMenuOpen] = useState(false);
    const [userMenuOpen, setUserMenuOpen] = useState(false);
    const activeHref = usePage().url;

    function logout() {
        router.post('/logout');
    }

    return (
        <div className="min-h-screen bg-slate-50 text-slate-900">
            {menuOpen && (
                <button
                    type="button"
                    aria-label="Close navigation"
                    className="fixed inset-0 z-30 bg-slate-950/30 lg:hidden"
                    onClick={() => setMenuOpen(false)}
                />
            )}
            <aside
                className={`fixed inset-y-0 left-0 z-40 flex w-72 flex-col border-r border-slate-200 bg-white transition-transform lg:translate-x-0 ${
                    menuOpen ? 'translate-x-0' : '-translate-x-full'
                }`}
            >
                <div className="flex h-20 items-center justify-between border-b border-slate-100 px-6">
                    <Link href="/dashboard" className="flex items-center gap-3">
                        <span className="flex size-10 items-center justify-center rounded-xl bg-teal-700 text-white shadow-sm shadow-teal-900/20">
                            <GraduationCap aria-hidden="true" size={22} />
                        </span>
                        <span className="min-w-0">
                            <span className="block truncate text-sm font-bold tracking-tight text-slate-950">
                                {schoolSettings?.school_name || appName}
                            </span>
                            <span className="mt-0.5 block text-xs text-slate-500">
                                School workspace
                            </span>
                        </span>
                    </Link>
                    <button
                        type="button"
                        aria-label="Close navigation"
                        className="rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:hidden"
                        onClick={() => setMenuOpen(false)}
                    >
                        <X size={18} />
                    </button>
                </div>

                <div className="px-4 pt-7">
                    <p className="px-3 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">
                        Workspace
                    </p>
                    <nav aria-label="Main navigation" className="mt-3 space-y-1">
                        {navigation.filter(({ permission }) => !permission || auth.user.permissions.includes(permission)).map(({ label, href, icon: Icon }) => {
                            const selected = activeHref === href;

                            return (
                                <Link
                                    key={href}
                                    href={href}
                                    onClick={() => setMenuOpen(false)}
                                    aria-current={selected ? 'page' : undefined}
                                    className={`flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition ${
                                        selected
                                            ? 'bg-teal-50 text-teal-800'
                                            : 'text-slate-600 hover:bg-slate-50 hover:text-slate-950'
                                    }`}
                                >
                                    <Icon size={18} strokeWidth={1.8} />
                                    {label}
                                </Link>
                            );
                        })}
                    </nav>
                </div>

                <div className="mt-auto p-4">
                    <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div className="flex items-center gap-2 text-sm font-semibold text-slate-800">
                            <BookOpen size={16} className="text-teal-700" />
                            School management
                        </div>
                        <p className="mt-2 text-xs leading-5 text-slate-500">
                            Manage school operations with access scoped by role and academic year.
                        </p>
                    </div>
                </div>
            </aside>

            <div className="lg:pl-72">
                <header className="sticky top-0 z-20 flex h-20 items-center justify-between border-b border-slate-200 bg-white/95 px-4 backdrop-blur sm:px-6 lg:px-9">
                    <div className="flex min-w-0 items-center gap-3">
                        <button
                            type="button"
                            aria-label="Open navigation"
                            className="rounded-xl p-2 text-slate-600 hover:bg-slate-100 lg:hidden"
                            onClick={() => setMenuOpen(true)}
                        >
                            <Menu size={20} />
                        </button>
                        <div className="hidden h-10 w-full max-w-sm items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 text-slate-400 sm:flex">
                            <Search size={17} />
                            <span className="text-sm">Search will be available with modules</span>
                        </div>
                        <div className="sm:hidden">
                            <p className="truncate text-sm font-semibold text-slate-900">{title}</p>
                        </div>
                    </div>

                    <div className="flex shrink-0 items-center gap-2 sm:gap-4">
                        <div className="hidden items-center gap-2 rounded-full border border-amber-200 bg-amber-50 px-3 py-1.5 md:flex">
                            <span className="size-1.5 rounded-full bg-amber-500" />
                            <span className={`text-xs font-medium ${activeAcademicYear ? 'text-teal-900' : 'text-amber-900'}`}>
                                {activeAcademicYear ? activeAcademicYear.name : 'Academic year not configured'}
                            </span>
                        </div>
                        <span
                            aria-label="Notifications are not configured in this phase"
                            title="Notifications are not configured yet"
                            className="rounded-xl p-2.5 text-slate-300"
                        >
                            <Bell size={19} />
                        </span>
                        <div className="relative">
                            <button
                                type="button"
                                aria-expanded={userMenuOpen}
                                className="flex items-center gap-2 rounded-xl p-1.5 text-left hover:bg-slate-50"
                                onClick={() => setUserMenuOpen((open) => !open)}
                            >
                                <span className="flex size-9 items-center justify-center rounded-full bg-teal-100 text-sm font-semibold text-teal-800">
                                    {auth.user.name.slice(0, 1).toUpperCase()}
                                </span>
                                <span className="hidden max-w-36 sm:block">
                                    <span className="block truncate text-sm font-semibold text-slate-800">
                                        {auth.user.name}
                                    </span>
                                    <span className="block truncate text-xs text-slate-500">
                                        {auth.user.email}
                                    </span>
                                </span>
                                <ChevronDown size={15} className="hidden text-slate-400 sm:block" />
                            </button>
                            {userMenuOpen && (
                                <div className="absolute right-0 mt-2 w-52 rounded-xl border border-slate-200 bg-white p-1.5 shadow-lg shadow-slate-900/10">
                                    <button
                                        type="button"
                                        onClick={logout}
                                        className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-medium text-rose-700 hover:bg-rose-50"
                                    >
                                        <LogOut size={16} />
                                        Sign out
                                    </button>
                                </div>
                            )}
                        </div>
                    </div>
                </header>

                <main className="mx-auto max-w-[1440px] px-4 py-8 sm:px-6 lg:px-9 lg:py-10">
                    <div className="mb-8 hidden sm:block">
                        <p className="mb-2 text-sm font-medium text-slate-500">
                            School workspace <span className="px-1 text-slate-300">/</span> Overview
                        </p>
                        <h1 className="text-2xl font-bold tracking-tight text-slate-950">{title}</h1>
                        {description && (
                            <p className="mt-1.5 text-sm text-slate-500">{description}</p>
                        )}
                    </div>
                    {children}
                </main>
            </div>
        </div>
    );
}
