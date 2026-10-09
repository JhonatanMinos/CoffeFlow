export type WorkspaceRole = 'owner' | 'admin' | 'manager' | 'member' | 'guest';

export type Workspace = {
    id: number;
    name: string;
    slug: string;
    isPersonal: boolean;
    role?: WorkspaceRole;
    roleLabel?: string;
    isCurrent?: boolean;
};

export type WorkspaceMember = {
    id: number;
    name: string;
    email: string;
    avatar?: string | null;
    role: WorkspaceRole;
    role_label: string;
};

export type WorkspaceInvitation = {
    code: string;
    email: string;
    role: WorkspaceRole;
    role_label: string;
    created_at: string;
};

export type WorkspaceInvitationContext = {
    code: string;
    workspaceName: string;
};

export type DashboardInvitation = {
    code: string;
    inviterName: string;
    workspace: {
        name: string;
        slug: string;
    };
};

export type WorkspacePermissions = {
    canUpdateWorkspace: boolean;
    canDeleteWorkspace: boolean;
    canAddMember: boolean;
    canUpdateMember: boolean;
    canRemoveMember: boolean;
    canCreateInvitation: boolean;
    canCancelInvitation: boolean;
};

export type RoleOption = {
    value: WorkspaceRole;
    label: string;
};
