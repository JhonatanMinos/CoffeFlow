import { Form } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { destroy } from '@/routes/workspaces';
import type { Workspace } from '@/types';

type Props = {
    workspace: Workspace;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function DeleteWorkspaceModal({
    workspace,
    open,
    onOpenChange,
}: Props) {
    const [confirmationName, setConfirmationName] = useState('');

    const canDeleteWorkspace = confirmationName === workspace.name;

    const handleOpenChange = (nextOpen: boolean) => {
        onOpenChange(nextOpen);

        if (!nextOpen) {
            setConfirmationName('');
        }
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent>
                <Form
                    key={String(open)}
                    {...destroy.form(workspace.slug)}
                    className="space-y-6"
                    onSuccess={() => handleOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Are you sure?</DialogTitle>
                                <DialogDescription>
                                    This action cannot be undone. This will
                                    permanently delete the workspace{' '}
                                    <strong>"{workspace.name}"</strong>.
                                </DialogDescription>
                            </DialogHeader>

                            <div className="space-y-4 py-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="confirmation-name">
                                        Type <strong>"{workspace.name}"</strong>{' '}
                                        to confirm
                                    </Label>
                                    <Input
                                        id="confirmation-name"
                                        name="name"
                                        data-test="delete-workspace-name"
                                        value={confirmationName}
                                        onChange={(event) =>
                                            setConfirmationName(
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Enter workspace name"
                                        autoComplete="off"
                                    />
                                    <InputError message={errors.name} />
                                </div>
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>

                                <Button
                                    variant="destructive"
                                    type="submit"
                                    data-test="delete-workspace-confirm"
                                    disabled={!canDeleteWorkspace || processing}
                                >
                                    Delete workspace
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
