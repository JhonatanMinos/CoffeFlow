import { Form } from "@inertiajs/react";
import { useState } from "react";
import InputError from "@/components/input-error";
import { Button } from "@/components/ui/button";
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { archive, update } from "@/routes/tasks";

type Task = {
    id: number;
    title: string;
    description: string | null;
};

type Props = {
    task: Task;
    currentWorkspace: string | null;
    project: string;
    canEdit: boolean;
    canArchive: boolean;
};

export default function TaskCardActions({
    task,
    currentWorkspace,
    project,
    canEdit,
    canArchive,
}: Props) {
    const [editOpen, setEditOpen] = useState(false);
    const [archiveOpen, setArchiveOpen] = useState(false);

    if (!currentWorkspace) {
        return null;
    }

    const routeArgs = {
        current_workspace: currentWorkspace,
        project,
        task: task.id,
    };

    return (
        <div className="mt-3 flex flex-wrap gap-2">
            {canEdit ? (
                <>
                    <Dialog open={editOpen} onOpenChange={setEditOpen}>
                        <DialogTrigger asChild>
                            <Button
                                type="button"
                                variant="outline"
                                aria-label={`Editar tarea: ${task.title}`}
                            >
                                Editar
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <Form
                                key={String(editOpen)}
                                {...update.form.put(routeArgs)}
                                className="space-y-6"
                                onSuccess={() => setEditOpen(false)}
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <DialogHeader>
                                            <DialogTitle>Editar tarea</DialogTitle>
                                            <DialogDescription>
                                                Actualiza el título o la descripción de esta tarea.
                                            </DialogDescription>
                                        </DialogHeader>

                                        <InputError
                                            id={`task-edit-error-${task.id}`}
                                            role="alert"
                                            message={errors.task}
                                        />

                                        <div className="grid gap-4">
                                            <div className="grid gap-2">
                                                <Label htmlFor={`task-edit-title-${task.id}`}>
                                                    Título
                                                </Label>
                                                <Input
                                                    id={`task-edit-title-${task.id}`}
                                                    name="title"
                                                    defaultValue={task.title}
                                                    maxLength={160}
                                                    required
                                                    aria-invalid={Boolean(errors.title)}
                                                    aria-describedby={
                                                        errors.title
                                                            ? `task-edit-title-error-${task.id}`
                                                            : undefined
                                                    }
                                                />
                                                <InputError
                                                    id={`task-edit-title-error-${task.id}`}
                                                    role="alert"
                                                    message={errors.title}
                                                />
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor={`task-edit-description-${task.id}`}>
                                                    Descripción (opcional)
                                                </Label>
                                                <textarea
                                                    id={`task-edit-description-${task.id}`}
                                                    name="description"
                                                    defaultValue={task.description ?? ""}
                                                    maxLength={5000}
                                                    rows={4}
                                                    aria-invalid={Boolean(errors.description)}
                                                    aria-describedby={
                                                        errors.description
                                                            ? `task-edit-description-error-${task.id}`
                                                            : undefined
                                                    }
                                                    className="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                                />
                                                <InputError
                                                    id={`task-edit-description-error-${task.id}`}
                                                    role="alert"
                                                    message={errors.description}
                                                />
                                            </div>
                                        </div>

                                        <DialogFooter className="gap-2">
                                            <DialogClose asChild>
                                                <Button
                                                    type="button"
                                                    variant="secondary"
                                                    disabled={processing}
                                                >
                                                    Cancelar
                                                </Button>
                                            </DialogClose>
                                            <Button type="submit" disabled={processing}>
                                                {processing ? "Guardando…" : "Guardar cambios"}
                                            </Button>
                                        </DialogFooter>
                                    </>
                                )}
                            </Form>
                        </DialogContent>
                    </Dialog>
                </>
            ) : null}

            {canArchive ? (
                <>
                    <Dialog open={archiveOpen} onOpenChange={setArchiveOpen}>
                        <DialogTrigger asChild>
                            <Button
                                type="button"
                                variant="outline"
                                aria-label={`Archivar tarea: ${task.title}`}
                            >
                                Archivar
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <Form
                                key={String(archiveOpen)}
                                {...archive.form.delete(routeArgs)}
                                className="space-y-6"
                                onSuccess={() => setArchiveOpen(false)}
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <DialogHeader>
                                            <DialogTitle>¿Archivar esta tarea?</DialogTitle>
                                            <DialogDescription>
                                                “{task.title}” dejará de aparecer en el tablero
                                                activo. Sus datos, sesiones de enfoque y demás
                                                historial se conservarán.
                                            </DialogDescription>
                                        </DialogHeader>

                                        <InputError
                                            id={`task-archive-error-${task.id}`}
                                            role="alert"
                                            message={errors.task}
                                        />

                                        <DialogFooter className="gap-2">
                                            <DialogClose asChild>
                                                <Button
                                                    type="button"
                                                    variant="secondary"
                                                    disabled={processing}
                                                >
                                                    Cancelar
                                                </Button>
                                            </DialogClose>
                                            <Button type="submit" disabled={processing}>
                                                {processing ? "Archivando…" : "Confirmar archivo"}
                                            </Button>
                                        </DialogFooter>
                                    </>
                                )}
                            </Form>
                        </DialogContent>
                    </Dialog>
                </>
            ) : null}
        </div>
    );
}
