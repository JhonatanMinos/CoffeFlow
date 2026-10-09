<?php

namespace App\Enums;

enum WorkspacePermission: string
{
    case UpdateWorkspace = 'workspace:update';
    case DeleteWorkspace = 'workspace:delete';

    case AddMember = 'member:add';
    case UpdateMember = 'member:update';
    case RemoveMember = 'member:remove';

    case CreateInvitation = 'invitation:create';
    case CancelInvitation = 'invitation:cancel';
}
