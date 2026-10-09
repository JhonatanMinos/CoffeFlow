import { Head, Link } from '@inertiajs/react';
import { Eye, LogOut, Pencil, Plus } from 'lucide-react';
import { useState } from 'react';
import CreateWorkspaceModal from '@/components/create-workspace-modal';
import Heading from '@/components/heading';
import LeaveWorkspaceModal from '@/components/leave-workspace-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { edit, index } from '@/routes/workspaces';
import type { Workspace } from '@/types';

type Props = {
    workspaces: Workspace[];
};

export default function WorkspacesIndex({ workspaces }: Props) {
    const [leaveWorkspaceDialogOpen, setLeaveWorkspaceDialogOpen] =
        useState(false);
    const [workspaceLeaving, setWorkspaceLeaving] = useState<Workspace | null>(
        null,
    );

    const openLeaveWorkspaceDialog = (workspace: Workspace) => {
        setWorkspaceLeaving(workspace);
        setLeaveWorkspaceDialogOpen(true);
    };

    return (
        <>
            <Head title="Espacios de trabajo" />

            <h1 className="sr-only">Espacios de trabajo</h1>

            <div className="flex flex-col space-y-6">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title="Espacios de trabajo"
                        description="Administra tus espacios y membresías"
                    />

                    <CreateWorkspaceModal>
                        <Button data-test="workspaces-new-workspace-button">
                            <Plus /> Nuevo espacio
                        </Button>
                    </CreateWorkspaceModal>
                </div>

                <div className="space-y-3">
                    {workspaces.map((workspace) => {
                        const canLeaveWorkspace =
                            !workspace.isPersonal && workspace.role !== 'owner';

                        return (
                            <div
                                key={workspace.id}
                                data-test="workspace-row"
                                className="flex items-center justify-between gap-4 rounded-lg border p-4"
                            >
                                <div className="flex items-center gap-4">
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <span className="font-medium">
                                                {workspace.name}
                                            </span>
                                            {workspace.isPersonal ? (
                                                <Badge variant="secondary">
                                                    Personal
                                                </Badge>
                                            ) : null}
                                        </div>
                                        <span className="text-sm text-muted-foreground">
                                            {workspace.roleLabel}
                                        </span>
                                    </div>
                                </div>

                                <TooltipProvider>
                                    <div className="flex items-center gap-2">
                                        {canLeaveWorkspace ? (
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        data-test="workspace-leave-button"
                                                        onClick={() =>
                                                            openLeaveWorkspaceDialog(
                                                                workspace,
                                                            )
                                                        }
                                                    >
                                                        <LogOut className="h-4 w-4" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p>Salir del espacio</p>
                                                </TooltipContent>
                                            </Tooltip>
                                        ) : null}

                                        {workspace.role === 'member' ? (
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        data-test="workspace-view-button"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={edit(
                                                                workspace.slug,
                                                            )}
                                                        >
                                                            <Eye className="h-4 w-4" />
                                                        </Link>
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p>Ver espacio</p>
                                                </TooltipContent>
                                            </Tooltip>
                                        ) : (
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        data-test="workspace-edit-button"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={edit(
                                                                workspace.slug,
                                                            )}
                                                        >
                                                            <Pencil className="h-4 w-4" />
                                                        </Link>
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p>Editar espacio</p>
                                                </TooltipContent>
                                            </Tooltip>
                                        )}
                                    </div>
                                </TooltipProvider>
                            </div>
                        );
                    })}

                    {workspaces.length === 0 ? (
                        <p className="py-8 text-center text-muted-foreground">
                            Todavía no perteneces a ningún espacio de trabajo.
                        </p>
                    ) : null}
                </div>
            </div>

            <LeaveWorkspaceModal
                workspace={workspaceLeaving}
                open={leaveWorkspaceDialogOpen}
                onOpenChange={setLeaveWorkspaceDialogOpen}
            />
        </>
    );
}

WorkspacesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Espacios de trabajo',
            href: index(),
        },
    ],
};
