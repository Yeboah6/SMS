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
    ChevronRight,
    Search,
    UsersRound,
    ShieldCheck,
    SlidersHorizontal,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';

const navigation = [
    { label: 'Overview', href: '/dashboard', icon: LayoutDashboard },
    {
        label: 'Academics',
        icon: BookOpen,
        children: [
            { label: 'Academic years', href: '/academic-years', icon: CalendarDays, permission: 'academic_years.view' },
            { label: 'Classes & sections', href: '/classes', icon: ClipboardList, permission: 'classes.view' },
            { label: 'Subjects', href: '/subjects', icon: BookOpen, permission: 'subjects.view' },
            { label: 'Attendance', href: '/attendance', icon: CalendarDays, permissions: ['attendance.view', 'portal.attendance.view'], roles: ['Principal', 'Teacher', 'Student', 'Parent', 'Super Admin'] },
            { label: 'Timetable', href: '/timetable', icon: CalendarDays, permissions: ['timetable.manage', 'timetable.view', 'portal.timetable.view'], roles: ['Principal', 'Teacher', 'Student', 'Parent', 'Super Admin'] },
            { label: 'Homework', href: '/homework', icon: ClipboardList, permissions: ['homework.view', 'portal.homework.view'], roles: ['Teacher', 'Principal', 'Student', 'Parent', 'Super Admin'] },
            { label: 'Exams & results', href: '/exams', icon: GraduationCap, permissions: ['exams.view', 'portal.results.view'], roles: ['Teacher', 'Principal', 'Student', 'Parent', 'Super Admin'] },
            { label: 'Promotions', href: '/promotions', icon: GraduationCap, permission: 'students.promote' },
        ],
    },
    {
        label: 'People',
        icon: UsersRound,
        children: [
            { label: 'Staff', href: '/staff', icon: UsersRound, permission: 'staff.view' },
            { label: 'Students', href: '/students', icon: GraduationCap, permission: 'students.view' },
            { label: 'Admissions', href: '/admissions', icon: ClipboardList, permission: 'admissions.view' },
            { label: 'Leave requests', href: '/leave-requests', icon: CalendarDays, permissions: ['leave.view', 'leave.apply', 'portal.leave.apply'], roles: ['Principal', 'Teacher', 'Student', 'Parent', 'Super Admin'] },
            { label: 'Certificates', href: '/certificates', icon: GraduationCap, permissions: ['certificates.view', 'certificates.issue', 'portal.certificates.request'], roles: ['Principal', 'Student', 'Parent', 'Super Admin'] },
        ],
    },
    {
        label: 'Finance',
        icon: ClipboardList,
        children: [
            { label: 'Fees & invoices', href: '/fees', activePaths: ['/fees', '/fee-heads', '/fee-structures', '/invoices'], icon: ClipboardList, permissions: ['fees.view', 'portal.fees.view'], roles: ['Accountant', 'Student', 'Parent', 'Super Admin'] },
            { label: 'Fee collection report', href: '/reports/financial', activePaths: ['/reports/financial', '/reports/financial/export'], matchNested: false, icon: ClipboardList, permission: 'reports.financial.view' },
            { label: 'Outstanding fees report', href: '/reports/financial/outstanding', activePaths: ['/reports/financial/outstanding', '/reports/financial/outstanding/export'], matchNested: false, icon: ClipboardList, permission: 'reports.financial.view' },
        ],
    },
    {
        label: 'Reports',
        icon: ClipboardList,
        children: [
            { label: 'Attendance report', href: '/reports/attendance', icon: ClipboardList, permission: 'reports.view' },
            { label: 'Academic results report', href: '/reports/academic', icon: GraduationCap, permission: 'results.view' },
        ],
    },
    {
        label: 'Administration',
        icon: SlidersHorizontal,
        children: [
            { label: 'School settings', href: '/settings', icon: SlidersHorizontal, permission: 'settings.view' },
            { label: 'Users', href: '/users', icon: GraduationCap, permission: 'users.view' },
            { label: 'Roles', href: '/roles', icon: BookOpen, permission: 'roles.view' },
            { label: 'Permissions', href: '/permissions', icon: ShieldCheck, permission: 'permissions.view' },
        ],
    },
    {
        label: 'Library',
        icon: BookOpen,
        children: [
            { label: 'Books inventory', href: '/supplies', activePaths: ['/supplies', '/supplies'], icon: BookOpen, permission: 'library.view', roles: ['Librarian', 'Super Admin'] },
            // { label: 'Loans', href: '/library/loans', icon: ClipboardList, permission: 'library.view', roles: ['Librarian', 'Super Admin'] },
            // { label: 'Library', href: '/library', matchNested: false, icon: BookOpen, permission: 'portal.library.view', roles: ['Student', 'Parent'] },
        ],
    },
    { label: 'Notices', href: '/notices', icon: Bell, permissions: ['notices.view', 'portal.notices.view'], roles: ['Principal', 'Teacher', 'Accountant', 'Librarian', 'Receptionist', 'Student', 'Parent', 'Super Admin'] },
];

function isNavigationItemVisible(item, user, isSuperAdmin) {
    return (!item.permission || isSuperAdmin || user.permissions.includes(item.permission))
        && (!item.permissions || isSuperAdmin || item.permissions.some((permission) => user.permissions.includes(permission)))
        && (!item.roles || item.roles.some((role) => user.roles.includes(role)));
}

function isNavigationItemActive(item, activePath) {
    const paths = item.activePaths || [item.href];

    return paths.some((path) => activePath === path || (item.matchNested !== false && activePath.startsWith(`${path}/`)));
}

export default function AppLayout({ children, title, description }) {
    const { appName, auth, activeAcademicYear, schoolSettings, schoolLogoUrl } = usePage().props;
    const [menuOpen, setMenuOpen] = useState(false);
    const [userMenuOpen, setUserMenuOpen] = useState(false);
    const activePath = usePage().url.split('?')[0];
    const isSuperAdmin = auth.user.roles.includes('Super Admin');
    const user = auth.user;
    const [expandedGroups, setExpandedGroups] = useState({});
    const visibleNavigation = navigation
        .map((item) => item.children
            ? { ...item, children: item.children.filter((child) => isNavigationItemVisible(child, auth.user, isSuperAdmin)) }
            : item)
        .filter((item) => item.children
            ? item.children.length > 0
            : isNavigationItemVisible(item, auth.user, isSuperAdmin));

    useEffect(() => {
        const activeGroup = navigation.find((item) => item.children?.some((child) => (
            isNavigationItemVisible(child, user, isSuperAdmin)
            && isNavigationItemActive(child, activePath)
        )));
        if (activeGroup) {
            setExpandedGroups((groups) => ({ ...groups, [activeGroup.label]: true }));
        }
    }, [activePath, isSuperAdmin, user]);

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
                <div className="flex h-20 shrink-0 items-center justify-between border-b border-slate-100 px-6">
                    <Link href="/dashboard" className="flex items-center gap-3">
                        {schoolLogoUrl ? (
                            <img src={schoolLogoUrl} alt="" className="size-10 shrink-0 rounded-xl border border-slate-200 bg-white object-contain p-1" />
                        ) : (
                            <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-teal-700 text-white shadow-sm shadow-teal-900/20">
                                <GraduationCap aria-hidden="true" size={22} />
                            </span>
                        )}
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

                <div className="flex min-h-0 flex-1 flex-col overflow-y-auto overscroll-contain px-4 pt-7">
                    <p className="px-3 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">
                        Workspace
                    </p>
                    <nav aria-label="Main navigation" className="mt-3 space-y-1 pb-4">
                        {visibleNavigation.map((item) => {
                            if (item.children) {
                                const GroupIcon = item.icon;
                                const expanded = expandedGroups[item.label] ?? false;

                                return (
                                    <div key={item.label}>
                                        <button
                                            type="button"
                                            aria-expanded={expanded}
                                            onClick={() => setExpandedGroups((groups) => ({
                                                ...groups,
                                                [item.label]: !expanded,
                                            }))}
                                            className="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-medium text-slate-600 transition hover:bg-slate-50 hover:text-slate-950"
                                        >
                                            <GroupIcon size={18} strokeWidth={1.8} />
                                            <span className="flex-1">{item.label}</span>
                                            <ChevronRight
                                                size={16}
                                                className={`transition-transform ${expanded ? 'rotate-90' : ''}`}
                                            />
                                        </button>
                                        {expanded && (
                                            <div className="ml-4 mt-1 space-y-1 border-l border-slate-200 pl-3">
                                                {item.children.map((child) => {
                                                    const ChildIcon = child.icon;
                                                    const selected = isNavigationItemActive(child, activePath);

                                                    return (
                                                        <Link
                                                            key={child.href}
                                                            href={child.href}
                                                            onClick={() => setMenuOpen(false)}
                                                            aria-current={selected ? 'page' : undefined}
                                                            className={`flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition ${
                                                                selected
                                                                    ? 'bg-teal-50 text-teal-800'
                                                                    : 'text-slate-600 hover:bg-slate-50 hover:text-slate-950'
                                                            }`}
                                                        >
                                                            <ChildIcon size={17} strokeWidth={1.8} />
                                                            {child.label}
                                                        </Link>
                                                    );
                                                })}
                                            </div>
                                        )}
                                    </div>
                                );
                            }

                            const Icon = item.icon;
                            const selected = isNavigationItemActive(item, activePath);

                            return (
                                <Link
                                    key={item.href}
                                    href={item.href}
                                    onClick={() => setMenuOpen(false)}
                                    aria-current={selected ? 'page' : undefined}
                                    className={`flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition ${
                                        selected
                                            ? 'bg-teal-50 text-teal-800'
                                            : 'text-slate-600 hover:bg-slate-50 hover:text-slate-950'
                                    }`}
                                >
                                    <Icon size={18} strokeWidth={1.8} />
                                    {item.label}
                                </Link>
                            );
                        })}
                    </nav>
                </div>

                <div className="shrink-0 p-4">
                    <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div className="flex items-center gap-2 text-sm font-semibold text-slate-800">
                            <BookOpen size={16} className="text-teal-700" />
                            School management
                        </div>
                        <p className="mt-2 text-xs leading-5 text-slate-500">
                            Everyday with God is Everyday in Victory.
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
