<?php

namespace App\Enums;

enum WorkspaceRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Manager = 'manager';
    case Member = 'member';
    case Guest = 'guest';

    /**
     * Get the display label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Propietario',
            self::Admin => 'Administrador',
            self::Manager => 'Gestor',
            self::Member => 'Miembro',
            self::Guest => 'Invitado',
        };
    }

    /**
     * Get all the permissions for this role.
     *
     * @return array<WorkspacePermission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Owner => WorkspacePermission::cases(),
            self::Admin => [
                WorkspacePermission::UpdateWorkspace,
                WorkspacePermission::CreateInvitation,
                WorkspacePermission::CancelInvitation,
            ],
            self::Manager, self::Member, self::Guest => [],
        };
    }

    /**
     * Determine if the role has the given permission.
     */
    public function hasPermission(WorkspacePermission $permission): bool
    {
        return in_array($permission, $this->permissions());
    }

    /**
     * Get the hierarchy level for this role.
     * Higher numbers indicate higher privileges.
     */
    public function level(): int
    {
        return match ($this) {
            self::Owner => 5,
            self::Admin => 4,
            self::Manager => 3,
            self::Member => 2,
            self::Guest => 1,
        };
    }

    /**
     * Check if this role is at least as privileged as another role.
     */
    public function isAtLeast(WorkspaceRole $role): bool
    {
        return $this->level() >= $role->level();
    }

    /**
     * Get the roles that can be assigned to workspace members (excludes Owner).
     *
     * @return array<array{value: string, label: string}>
     */
    public static function assignable(): array
    {
        return collect(self::cases())
            ->filter(fn (self $role) => $role !== self::Owner)
            ->map(fn (self $role) => ['value' => $role->value, 'label' => $role->label()])
            ->values()
            ->toArray();
    }
}
