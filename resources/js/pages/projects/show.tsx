import { Form, Head, usePage } from '@inertiajs/react';
import { CircleDashed, Plus } from 'lucide-react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, show } from '@/routes/projects';
import type { Board } from '@/types';

type ProjectDetails = {
    id: number;
    name: string;
    slug: string;
    methodology: 'kanban';
    status: 'active' | 'archived';
};

type Props = {
    project: ProjectDetails;
    board: Omit<Board, 'columns'> & {
        columns: Array<
            Board['columns'][number] & {
                tasks: Array<{
                    id: number;
                    title: string;
                    description: string | null;
                }>;
            }
        >;
    };
    canCreateTask: boolean;
};

export default function ProjectShow({ project, board, canCreateTask }: Props) {
    const { currentWorkspace } = usePage().props;
    const taskStoreUrl = currentWorkspace
        ? `/${encodeURIComponent(currentWorkspace.slug)}/projects/${encodeURIComponent(project.slug)}/tasks`
        : null;

    return (
        <>
            <Head title={project.name} />

            <div className="flex min-h-0 flex-1 flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading title={project.name} description={board.name} />
                    <Badge variant="secondary">Kanban</Badge>
                </div>

                <section
                    aria-label={`Tablero ${board.name}`}
                    className="flex min-h-0 gap-4 overflow-x-auto pb-4"
                >
                    {board.columns.map((column) => (
                        <article
                            key={column.id}
                            className="flex min-h-72 w-72 shrink-0 flex-col rounded-xl border bg-muted/30 p-3"
                        >
                            <header className="flex items-center justify-between gap-3 px-1 pb-3">
                                <h2 className="font-medium">{column.name}</h2>
                                {column.wipLimit !== null ? (
                                    <span className="text-xs text-muted-foreground">
                                        Límite {column.wipLimit}
                                    </span>
                                ) : null}
                            </header>

                            <div className="flex flex-1 flex-col gap-3">
                                {column.tasks.length > 0 ? (
                                    column.tasks.map((task) => (
                                        <article
                                            key={task.id}
                                            className="rounded-lg border bg-background p-3"
                                        >
                                            <h3 className="font-medium">{task.title}</h3>
                                            {task.description ? (
                                                <p className="mt-2 whitespace-pre-wrap text-sm text-muted-foreground">
                                                    {task.description}
                                                </p>
                                            ) : null}
                                        </article>
                                    ))
                                ) : (
                                    <div className="flex flex-1 flex-col items-center justify-center rounded-lg border border-dashed bg-background/60 p-5 text-center text-muted-foreground">
                                        <CircleDashed
                                            aria-hidden="true"
                                            className="mb-3 size-6"
                                        />
                                        <p className="text-sm">Sin tareas</p>
                                    </div>
                                )}
                            </div>

                            {canCreateTask && taskStoreUrl ? (
                                <details className="rounded-lg border bg-background p-3">
                                    <summary className="flex cursor-pointer list-none items-center gap-2 rounded-sm text-sm font-medium focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none">
                                        <Plus aria-hidden="true" className="size-4" />
                                        Crear tarea
                                    </summary>
                                    <Form
                                        action={taskStoreUrl}
                                        method="post"
                                        className="mt-3 grid gap-3"
                                        resetOnSuccess
                                    >
                                        {({ errors, processing }) => (
                                            <>
                                                <input
                                                    type="hidden"
                                                    name="board_id"
                                                    value={board.id}
                                                />
                                                <input
                                                    type="hidden"
                                                    name="column_id"
                                                    value={column.id}
                                                />
                                                <div className="grid gap-2">
                                                    <Label htmlFor={`task-title-${column.id}`}>
                                                        Título
                                                    </Label>
                                                    <Input
                                                        id={`task-title-${column.id}`}
                                                        name="title"
                                                        maxLength={160}
                                                        required
                                                    />
                                                    <InputError message={errors.title} />
                                                </div>
                                                <div className="grid gap-2">
                                                    <Label htmlFor={`task-description-${column.id}`}>
                                                        Descripción (opcional)
                                                    </Label>
                                                    <textarea
                                                        id={`task-description-${column.id}`}
                                                        name="description"
                                                        maxLength={5000}
                                                        rows={3}
                                                        className="border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                                                    />
                                                    <InputError message={errors.description} />
                                                </div>
                                                <Button type="submit" disabled={processing}>
                                                    Crear tarea
                                                </Button>
                                            </>
                                        )}
                                    </Form>
                                </details>
                            ) : null}
                        </article>
                    ))}
                </section>
            </div>
        </>
    );
}

ProjectShow.layout = (
    props: Props & { currentWorkspace?: { slug: string } | null },
) => ({
    breadcrumbs: props.currentWorkspace
        ? [
              {
                  title: 'Proyectos',
                  href: index(props.currentWorkspace.slug),
              },
              {
                  title: props.project.name,
                  href: show([props.currentWorkspace.slug, props.project.slug]),
              },
          ]
        : [],
});
