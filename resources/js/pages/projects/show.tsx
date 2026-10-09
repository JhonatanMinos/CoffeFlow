import { Head } from '@inertiajs/react';
import { CircleDashed } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
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
    board: Board;
};

export default function ProjectShow({ project, board }: Props) {
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

                            <div className="flex flex-1 flex-col items-center justify-center rounded-lg border border-dashed bg-background/60 p-5 text-center text-muted-foreground">
                                <CircleDashed
                                    aria-hidden="true"
                                    className="mb-3 size-6"
                                />
                                <p className="text-sm">Sin tareas</p>
                            </div>
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
