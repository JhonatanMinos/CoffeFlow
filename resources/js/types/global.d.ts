import type { Auth } from '@/types/auth';
import type { Workspace } from '@/types/workspaces';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            currentWorkspace: Workspace | null;
            workspaces: Workspace[];
            [key: string]: unknown;
        };
    }
}
