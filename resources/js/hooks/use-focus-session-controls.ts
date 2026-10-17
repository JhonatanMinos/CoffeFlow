import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

export type ActiveFocusSession = {
    id: number;
    taskId: number | null;
    status: 'running' | 'paused';
    targetDurationSeconds: number | null;
    confirmedSeconds: number;
    intervalStartedAt: string | null;
    lastConfirmedAt: string | null;
};

type FocusAction = 'pause' | 'resume' | 'finish' | 'interrupt';

type HeartbeatResponse = {
    status: 'running' | 'paused';
    confirmedSeconds: number;
    targetDurationSeconds: number | null;
    confirmedAt: string | null;
    intervalStartedAt: string | null;
};

class HeartbeatFailure extends Error {}

const heartbeatIntervalMs = 20_000;

function readXsrfToken(): string | null {
    const cookie = document.cookie
        .split('; ')
        .find((item) => item.startsWith('XSRF-TOKEN='));

    return cookie
        ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length))
        : null;
}

function formatDuration(seconds: number): string {
    const safeSeconds = Math.max(0, Math.floor(seconds));
    const hours = Math.floor(safeSeconds / 3600);
    const minutes = Math.floor((safeSeconds % 3600) / 60);
    const remainder = safeSeconds % 60;

    return [hours, minutes, remainder]
        .map((value) => value.toString().padStart(2, '0'))
        .join(':');
}

function heartbeatError(status: number): string {
    if (status === 401) {
        return 'Tu sesión de usuario expiró. Inicia sesión y vuelve a cargar esta página.';
    }
    if (status === 403 || status === 404) {
        return 'El servidor ya no permite actualizar esta sesión. Recarga para sincronizar su estado.';
    }
    if (status === 419) {
        return 'La sesión de seguridad expiró. Recarga la página antes de continuar.';
    }
    if (status === 422) {
        return 'La sesión cambió de estado en otro lugar. Recarga para ver el estado actual.';
    }

    return 'No se pudo confirmar el tiempo. No se acreditará tiempo adicional hasta sincronizar la sesión.';
}

export function useFocusSessionControls(
    initialSession: ActiveFocusSession | null,
    workspaceSlug: string | null,
) {
    const [status, setStatus] = useState(initialSession?.status ?? null);
    const [confirmedSeconds, setConfirmedSeconds] = useState(
        initialSession?.confirmedSeconds ?? 0,
    );
    const [targetDurationSeconds, setTargetDurationSeconds] = useState(
        initialSession?.targetDurationSeconds ?? null,
    );
    const [pendingAction, setPendingAction] = useState<FocusAction | null>(
        null,
    );
    const [error, setError] = useState<string | null>(null);
    const [result, setResult] = useState<string | null>(null);
    const [heartbeatEnabled, setHeartbeatEnabled] = useState(true);
    const generation = useRef(0);
    const request = useRef<AbortController | null>(null);
    const inFlight = useRef(false);
    const pending = useRef(false);
    const previousSessionId = useRef<number | null>(initialSession?.id ?? null);
    const closedLocally = useRef(false);
    const terminalOutcome = useRef<string | null>(null);

    useEffect(
        () =>
            router.on('flash', (event) => {
                const outcome = (event as CustomEvent).detail?.flash
                    ?.focusSessionResult;
                const message =
                    outcome === 'completed'
                        ? 'Sesión completada.'
                        : outcome === 'interrupted'
                          ? 'Sesión interrumpida.'
                          : null;

                if (message) {
                    terminalOutcome.current = message;
                    setResult(message);
                }
            }),
        [],
    );

    useEffect(() => {
        if (
            initialSession?.id &&
            previousSessionId.current !== initialSession.id
        ) {
            previousSessionId.current = initialSession.id;
            closedLocally.current = false;
            setResult(null);
        } else if (
            !initialSession &&
            previousSessionId.current !== null &&
            !closedLocally.current
        ) {
            previousSessionId.current = null;
            setResult(
                'La sesión dejó de estar activa en otra pestaña. El estado se actualizó desde el servidor.',
            );
        }
        setStatus(initialSession?.status ?? null);
        setConfirmedSeconds(initialSession?.confirmedSeconds ?? 0);
        setTargetDurationSeconds(initialSession?.targetDurationSeconds ?? null);
        setHeartbeatEnabled(true);
        setError(null);
    }, [
        initialSession?.id,
        initialSession?.status,
        initialSession?.confirmedSeconds,
        initialSession?.targetDurationSeconds,
    ]);

    useEffect(() => {
        generation.current += 1;
        const currentGeneration = generation.current;

        if (
            !initialSession ||
            status !== 'running' ||
            !workspaceSlug ||
            !heartbeatEnabled ||
            pendingAction
        ) {
            return () => {
                generation.current += 1;
                request.current?.abort();
                request.current = null;
                inFlight.current = false;
            };
        }

        const controller = new AbortController();
        const interval = window.setInterval(async () => {
            if (inFlight.current || pending.current) {
                return;
            }

            inFlight.current = true;
            request.current = controller;

            try {
                const xsrfToken = readXsrfToken();
                if (!xsrfToken) {
                    throw new HeartbeatFailure(
                        'No se encontró el token de seguridad.',
                    );
                }

                const response = await fetch(
                    `/${encodeURIComponent(workspaceSlug)}/focus-sessions/${initialSession.id}/heartbeat`,
                    {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-XSRF-TOKEN': xsrfToken,
                        },
                        signal: controller.signal,
                    },
                );
                if (!response.ok) {
                    if ([403, 404, 422].includes(response.status)) {
                        router.reload({
                            only: ['activeFocusSession'],
                            preserveUrl: true,
                        });
                    }
                    throw new HeartbeatFailure(heartbeatError(response.status));
                }

                const data = (await response.json()) as HeartbeatResponse;
                if (
                    typeof data.confirmedSeconds !== 'number' ||
                    (data.status !== 'running' && data.status !== 'paused')
                ) {
                    throw new HeartbeatFailure(
                        'El servidor devolvió un estado de sesión inválido.',
                    );
                }

                if (generation.current !== currentGeneration) {
                    return;
                }

                setConfirmedSeconds(data.confirmedSeconds);
                setTargetDurationSeconds(data.targetDurationSeconds);
                if (data.status === 'paused') {
                    setStatus('paused');
                    setHeartbeatEnabled(false);
                    setError(
                        'La confirmación llegó tarde y el tiempo sin confirmar quedó excluido. Reanuda explícitamente para continuar.',
                    );
                }
            } catch (caught) {
                if (
                    controller.signal.aborted ||
                    generation.current !== currentGeneration
                ) {
                    return;
                }

                setHeartbeatEnabled(false);
                setError(
                    caught instanceof HeartbeatFailure
                        ? caught.message
                        : 'No se pudo confirmar el tiempo. Recarga para sincronizar la sesión.',
                );
            } finally {
                inFlight.current = false;
                if (request.current === controller) {
                    request.current = null;
                }
            }
        }, heartbeatIntervalMs);

        return () => {
            window.clearInterval(interval);
            generation.current += 1;
            controller.abort();
            request.current = null;
            inFlight.current = false;
        };
    }, [
        initialSession?.id,
        initialSession,
        status,
        workspaceSlug,
        heartbeatEnabled,
        pendingAction,
    ]);

    function refresh(): void {
        setHeartbeatEnabled(false);
        router.reload({
            only: ['activeFocusSession'],
            preserveUrl: true,
            onSuccess: () => {
                setHeartbeatEnabled(true);
                setError(null);
            },
        });
    }

    function runAction(action: FocusAction): void {
        if (!initialSession || !workspaceSlug || pending.current) {
            return;
        }

        if (
            (action === 'pause' || action === 'finish') &&
            status !== 'running'
        ) {
            return;
        }
        if (action === 'resume' && status !== 'paused') {
            return;
        }
        if (action === 'finish' && targetDurationSeconds === null) {
            return;
        }

        generation.current += 1;
        request.current?.abort();
        request.current = null;
        pending.current = true;
        setPendingAction(action);
        setError(null);
        setResult(null);
        terminalOutcome.current = null;

        router.post(
            `/${encodeURIComponent(workspaceSlug)}/focus-sessions/${initialSession.id}/${action}`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    if (action === 'pause') {
                        setStatus('paused');
                    } else if (action === 'resume') {
                        setStatus('running');
                        setHeartbeatEnabled(true);
                    } else if (action === 'finish') {
                        closedLocally.current = true;
                        setResult(
                            terminalOutcome.current ??
                                'La sesión terminó. El servidor registró su resultado usando el objetivo y el tiempo confirmado.',
                        );
                        setStatus(null);
                    } else {
                        closedLocally.current = true;
                        setResult(
                            terminalOutcome.current ??
                                'La sesión se cerró. El tiempo confirmado se conserva.',
                        );
                        setStatus(null);
                    }
                },
                onError: (errors) => {
                    setError(
                        errors.focus_session ??
                            'No se pudo cambiar la sesión. Recarga para sincronizar su estado.',
                    );
                    router.reload({
                        only: ['activeFocusSession'],
                        preserveUrl: true,
                    });
                },
                onFinish: () => {
                    pending.current = false;
                    setPendingAction(null);
                },
            },
        );
    }

    return {
        status,
        confirmedSeconds,
        targetDurationSeconds,
        pendingAction,
        error,
        result,
        refresh,
        runAction,
        formatDuration,
    };
}
