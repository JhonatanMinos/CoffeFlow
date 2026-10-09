import { Form, Head, Link, usePage } from '@inertiajs/react';
import { FolderKanban, Plus } from 'lucide-react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, show, store } from '@/routes/projects';
import type { PaginatedProjects } from '@/types';

type Props = {
    projects: PaginatedProjects;
    canCreateProject: boolean;
};

export default function ProjectsIndex({ projects, canCreateProject }: Props) {
    const { currentWorkspace } = usePage().props;

    if (!currentWorkspace) {
        return null;
    }

    return (
        <>
            <Head title="Proyectos" />

            <div className="flex flex-col gap-8 p-4">
                <Heading
                    title="Proyectos"
                    description={`Trabajo compartido en ${currentWorkspace.name}`}
                />

                {canCreateProject ? (
                    <Form
                        {...store.form(currentWorkspace.slug)}
                        className="rounded-xl border bg-card p-5"
                        resetOnSuccess
                    >
                        {({ errors, processing }) => (
                            <div className="flex flex-col gap-4 sm:flex-row sm:items-end">
                                <div className="grid flex-1 gap-2">
                                    <Label htmlFor="project-name">
                                        Nuevo proyecto
                                    </Label>
                                    <Input
                                        id="project-name"
                                        name="name"
                                        placeholder="Nombre del proyecto"
                                        maxLength={120}
                                        required
                                    />
                                    <InputError message={errors.name} />
                                </div>
                                <Button type="submit" disabled={processing}>
                                    <Plus aria-hidden="true" />
                                    Crear proyecto
                                </Button>
                            </div>
                        )}
                    </Form>
                ) : null}

                {projects.data.length > 0 ? (
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {projects.data.map((project) => (
                            <article
                                key={project.id}
                                className="rounded-xl border bg-card p-5"
                            >
                                <div className="flex items-start justify-between gap-4">
                                    <FolderKanban
                                        aria-hidden="true"
                                        className="size-6 text-amber-600"
                                    />
                                    <Badge variant="secondary">Kanban</Badge>
                                </div>
                                <h2 className="mt-5 text-lg font-semibold">
                                    <Link
                                        href={show([
                                            currentWorkspace.slug,
                                            project.slug,
                                        ])}
                                        className="rounded-sm hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    >
                                        {project.name}
                                    </Link>
                                </h2>
                                <p className="mt-2 text-sm text-muted-foreground">
                                    Creado por {project.createdBy}
                                </p>
                            </article>
                        ))}
                    </div>
                ) : (
                    <div className="rounded-xl border border-dashed p-10 text-center">
                        <FolderKanban
                            aria-hidden="true"
                            className="mx-auto size-9 text-muted-foreground"
                        />
                        <h2 className="mt-4 font-semibold">
                            Todavía no hay proyectos
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Crea el primero para empezar a organizar el trabajo
                            del equipo.
                        </p>
                    </div>
                )}

                {projects.last_page > 1 ? (
                    <nav
                        aria-label="Paginación de proyectos"
                        className="flex justify-end gap-2"
                    >
                        {projects.prev_page_url ? (
                            <Button variant="outline" asChild>
                                <Link href={projects.prev_page_url}>
                                    Anterior
                                </Link>
                            </Button>
                        ) : null}
                        {projects.next_page_url ? (
                            <Button variant="outline" asChild>
                                <Link href={projects.next_page_url}>
                                    Siguiente
                                </Link>
                            </Button>
                        ) : null}
                    </nav>
                ) : null}
            </div>
        </>
    );
}

ProjectsIndex.layout = (props: {
    currentWorkspace?: { slug: string } | null;
}) => ({
    breadcrumbs: [
        {
            title: 'Proyectos',
            href: props.currentWorkspace
                ? index(props.currentWorkspace.slug)
                : '/',
        },
    ],
});
