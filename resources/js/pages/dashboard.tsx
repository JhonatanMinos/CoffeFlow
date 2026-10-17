import { Form, Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import FocusSessionControls from '@/components/focus/focus-session-controls';
import PendingInvitationsModal from '@/components/pending-invitations-modal';
import InputError from '@/components/input-error';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import type { DashboardInvitation } from '@/types';
import type { ActiveFocusSession } from '@/hooks/use-focus-session-controls';

type Props = {
    pendingInvitations?: DashboardInvitation[];
    activeFocusSession: ActiveFocusSession | null;
};

export default function Dashboard({
    pendingInvitations = [],
    activeFocusSession,
}: Props) {
    const { currentWorkspace } = usePage().props;
    const [showInvitations, setShowInvitations] = useState(
        pendingInvitations.length > 0,
    );
    const focusSessionUrl = currentWorkspace
        ? `/${encodeURIComponent(currentWorkspace.slug)}/focus-sessions`
        : null;

    return (
        <>
            <Head title="Dashboard" />
            <PendingInvitationsModal
                invitations={pendingInvitations}
                open={pendingInvitations.length > 0 && showInvitations}
                onOpenChange={setShowInvitations}
            />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <section aria-label="Enfoque" className="grid gap-3">
                    <FocusSessionControls
                        activeSession={activeFocusSession}
                        workspaceSlug={currentWorkspace?.slug ?? null}
                    />
                    {focusSessionUrl ? (
                        <Form
                            action={focusSessionUrl}
                            method="post"
                            className="grid justify-items-start gap-2"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <InputError
                                        id="focus-session-error"
                                        role="alert"
                                        message={errors.focus_session}
                                    />
                                    <Button
                                        type="submit"
                                        disabled={
                                            processing ||
                                            activeFocusSession !== null
                                        }
                                        aria-describedby={
                                            errors.focus_session
                                                ? 'focus-session-error'
                                                : undefined
                                        }
                                    >
                                        Iniciar sesión de enfoque
                                    </Button>
                                </>
                            )}
                        </Form>
                    ) : null}
                </section>
                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    <div className="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                    </div>
                    <div className="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                    </div>
                    <div className="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                    </div>
                </div>
                <div className="relative min-h-[100vh] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border">
                    <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                </div>
            </div>
        </>
    );
}

Dashboard.layout = (props: { currentWorkspace?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: props.currentWorkspace
                ? dashboard(props.currentWorkspace.slug)
                : '/',
        },
    ],
});
