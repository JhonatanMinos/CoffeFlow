import { useFocusSessionControls } from '@/hooks/use-focus-session-controls';
import type { ActiveFocusSession } from '@/hooks/use-focus-session-controls';
import { Button } from '@/components/ui/button';

type Props = {
    activeSession: ActiveFocusSession | null;
    workspaceSlug: string | null;
};

export default function FocusSessionControls({
    activeSession,
    workspaceSlug,
}: Props) {
    const {
        status,
        confirmedSeconds,
        targetDurationSeconds,
        pendingAction,
        error,
        result,
        refresh,
        runAction,
        formatDuration,
    } = useFocusSessionControls(activeSession, workspaceSlug);

    if (!activeSession && !result && !error) {
        return null;
    }

    return (
        <section
            aria-label="Controles de la sesión de enfoque"
            className="grid gap-3 rounded-lg border bg-background p-4"
        >
            {activeSession && status ? (
                <>
                    <div className="grid gap-1">
                        <p className="font-medium">
                            Sesión{' '}
                            {status === 'running' ? 'en curso' : 'en pausa'}
                        </p>
                        <p className="text-sm">
                            Tiempo confirmado:{' '}
                            {formatDuration(confirmedSeconds)}
                        </p>
                        {targetDurationSeconds === null ? (
                            <p className="text-sm text-muted-foreground">
                                Esta sesión anterior no tiene un objetivo
                                guardado y no se puede finalizar como completa.
                            </p>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                Objetivo:{' '}
                                {formatDuration(targetDurationSeconds)}
                            </p>
                        )}
                    </div>

                    {status === 'paused' ? (
                        <p className="text-sm text-muted-foreground">
                            El tiempo sin confirmar no se suma. Reanuda
                            explícitamente para continuar.
                        </p>
                    ) : null}

                    <div className="flex flex-wrap gap-2">
                        {status === 'running' ? (
                            <>
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={pendingAction !== null}
                                    onClick={() => runAction('pause')}
                                >
                                    {pendingAction === 'pause'
                                        ? 'Pausando…'
                                        : 'Pausar'}
                                </Button>
                                {targetDurationSeconds !== null ? (
                                    <Button
                                        type="button"
                                        disabled={pendingAction !== null}
                                        onClick={() => runAction('finish')}
                                    >
                                        {pendingAction === 'finish'
                                            ? 'Finalizando…'
                                            : 'Finalizar sesión'}
                                    </Button>
                                ) : null}
                            </>
                        ) : (
                            <Button
                                type="button"
                                variant="outline"
                                disabled={pendingAction !== null}
                                onClick={() => runAction('resume')}
                            >
                                {pendingAction === 'resume'
                                    ? 'Reanudando…'
                                    : 'Reanudar'}
                            </Button>
                        )}
                        <Button
                            type="button"
                            variant="destructive"
                            disabled={pendingAction !== null}
                            onClick={() => runAction('interrupt')}
                        >
                            {pendingAction === 'interrupt'
                                ? 'Interrumpiendo…'
                                : 'Interrumpir sesión'}
                        </Button>
                    </div>

                    {status === 'running' && targetDurationSeconds !== null ? (
                        <p className="text-sm text-muted-foreground">
                            Si aún no alcanzaste el objetivo confirmado,
                            finalizar registrará la sesión como interrumpida.
                        </p>
                    ) : null}
                </>
            ) : null}

            {error ? (
                <div className="grid justify-items-start gap-2">
                    <p role="alert" className="text-sm text-destructive">
                        {error}
                    </p>
                    <Button
                        type="button"
                        variant="outline"
                        disabled={pendingAction !== null}
                        onClick={refresh}
                    >
                        Recargar estado
                    </Button>
                </div>
            ) : null}

            {result ? (
                <p
                    role="status"
                    aria-live="polite"
                    className="text-sm font-medium"
                >
                    {result}
                </p>
            ) : null}
        </section>
    );
}
