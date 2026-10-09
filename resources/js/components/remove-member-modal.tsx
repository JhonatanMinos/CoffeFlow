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
import { destroy as destroyMember } from '@/routes/workspaces/members';
import type { Workspace, WorkspaceMember } from '@/types';

type Props = {
    workspace: Workspace;
    member: WorkspaceMember | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function RemoveMemberModal({
    workspace,
    member,
    open,
    onOpenChange,
}: Props) {
    const [processing, setProcessing] = useState(false);

    const removeMember = () => {
        if (!member) {
            return;
        }

        router.visit(destroyMember([workspace.slug, member.id]), {
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Remove workspace member</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to remove{' '}
                        <strong>{member?.name}</strong> from this workspace?
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>

                    <Button
                        variant="destructive"
                        data-test="remove-member-confirm"
                        disabled={processing}
                        onClick={removeMember}
                    >
                        Remove member
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
