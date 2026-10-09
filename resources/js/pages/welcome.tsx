import { Head, Link, usePage } from '@inertiajs/react';
import { Coffee, Columns3, Sprout, Timer } from 'lucide-react';
import { dashboard, login, register } from '@/routes';

const pillars = [
    {
        icon: Timer,
        title: 'Focus',
        description:
            'Sesiones de concentración claras, medibles y respetuosas con tus pausas.',
    },
    {
        icon: Columns3,
        title: 'Flow',
        description:
            'Proyectos y tableros para que el trabajo avance sin perder contexto.',
    },
    {
        icon: Sprout,
        title: 'Grow',
        description:
            'Progreso verificable, logros y estadísticas privadas por defecto.',
    },
];

export default function Welcome() {
    const { auth, currentWorkspace } = usePage().props;
    const dashboardUrl = currentWorkspace
        ? dashboard(currentWorkspace.slug)
        : '/';

    return (
        <>
            <Head title="Inicio" />

            <main className="min-h-screen bg-stone-950 text-stone-100">
                <div className="mx-auto flex min-h-screen max-w-6xl flex-col px-6 py-6 lg:px-10">
                    <header className="flex items-center justify-between">
                        <Link
                            href="/"
                            className="flex items-center gap-3 font-semibold"
                        >
                            <span className="flex size-10 items-center justify-center rounded-xl bg-amber-500 text-stone-950">
                                <Coffee aria-hidden="true" className="size-5" />
                            </span>
                            <span className="text-lg">CoffeeFlow</span>
                        </Link>

                        <nav
                            aria-label="Acceso"
                            className="flex items-center gap-2"
                        >
                            {auth.user ? (
                                <Link
                                    href={dashboardUrl}
                                    className="rounded-lg bg-amber-500 px-4 py-2 text-sm font-medium text-stone-950 transition hover:bg-amber-400"
                                >
                                    Ir al panel
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href={login()}
                                        className="rounded-lg px-4 py-2 text-sm font-medium text-stone-300 transition hover:bg-stone-800 hover:text-white"
                                    >
                                        Iniciar sesión
                                    </Link>
                                    <Link
                                        href={register()}
                                        className="rounded-lg bg-amber-500 px-4 py-2 text-sm font-medium text-stone-950 transition hover:bg-amber-400"
                                    >
                                        Crear cuenta
                                    </Link>
                                </>
                            )}
                        </nav>
                    </header>

                    <section className="flex flex-1 flex-col justify-center py-20 lg:py-28">
                        <div className="max-w-3xl">
                            <p className="mb-5 text-sm font-semibold tracking-[0.22em] text-amber-400 uppercase">
                                Enfócate · Avanza · Crece
                            </p>
                            <h1 className="text-5xl leading-[1.05] font-semibold tracking-tight text-balance sm:text-6xl lg:text-7xl">
                                Tu trabajo fluye mejor con el ritmo correcto.
                            </h1>
                            <p className="mt-7 max-w-2xl text-lg leading-8 text-stone-400">
                                CoffeeFlow reúne concentración personal y
                                gestión de proyectos para equipos de software,
                                sin convertir cada día en una carrera de horas.
                            </p>

                            {!auth.user ? (
                                <div className="mt-9 flex flex-wrap gap-3">
                                    <Link
                                        href={register()}
                                        className="rounded-xl bg-amber-500 px-5 py-3 font-medium text-stone-950 transition hover:bg-amber-400"
                                    >
                                        Empezar gratis
                                    </Link>
                                    <Link
                                        href={login()}
                                        className="rounded-xl border border-stone-700 px-5 py-3 font-medium text-stone-200 transition hover:border-stone-500 hover:bg-stone-900"
                                    >
                                        Ya tengo una cuenta
                                    </Link>
                                </div>
                            ) : null}
                        </div>

                        <div className="mt-16 grid gap-4 md:grid-cols-3">
                            {pillars.map(
                                ({ icon: Icon, title, description }) => (
                                    <article
                                        key={title}
                                        className="rounded-2xl border border-stone-800 bg-stone-900/60 p-6"
                                    >
                                        <Icon
                                            aria-hidden="true"
                                            className="mb-5 size-6 text-amber-400"
                                        />
                                        <h2 className="text-lg font-semibold">
                                            {title}
                                        </h2>
                                        <p className="mt-2 leading-7 text-stone-400">
                                            {description}
                                        </p>
                                    </article>
                                ),
                            )}
                        </div>
                    </section>
                </div>
            </main>
        </>
    );
}
