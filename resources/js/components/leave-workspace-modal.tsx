import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { leave as leaveWorkspaceAction } from '@/routes/workspaces';
import type { Workspace } from '@/types';

type Props = {
    workspace: Workspace | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function LeaveWorkspaceModal({
    workspace,
    open,
    onOpenChange,
}: Props) {
    const [processing, setProcessing] = useState(false);

    const leaveWorkspace = () => {
        if (!workspace) {
            return;
        }

        router.visit(leaveWorkspaceAction(workspace.slug), {
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Leave workspace</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to leave{' '}
                        <strong>{workspace?.name}</strong>?
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>

                    <Button
                        variant="destructive"
                        data-test="leave-workspace-confirm"
                        disabled={processing}
                        onClick={leaveWorkspace}
                    >
                        Leave workspace
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
