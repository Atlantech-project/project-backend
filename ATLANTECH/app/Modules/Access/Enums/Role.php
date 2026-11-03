<?php

namespace App\Modules\Access\Enums;

/**
 * `user_role_assignments.role`: the twelve port roles (DEC-36: one active role per user).
 */
enum Role: string
{
    case PortGm = 'PORT_GM';
    case PortStaff = 'PORT_STAFF';
    case MarineSafetyOfficer = 'MARINE_SAFETY_OFFICER';
    case PilotageManager = 'PILOTAGE_MANAGER';
    case PilotageOfficer = 'PILOTAGE_OFFICER';
    case MarinePilot = 'MARINE_PILOT';
    case TugManager = 'TUG_MANAGER';
    case TugOfficer = 'TUG_OFFICER';
    case TugMaster = 'TUG_MASTER';
    case TerminalAdmin = 'TERMINAL_ADMIN';
    case TerminalOfficer = 'TERMINAL_OFFICER';
    case TerminalSupervisor = 'TERMINAL_SUPERVISOR';

    /**
     * Terminal-scoped roles act only on their own terminal (FR-AC-15).
     */
    public function isTerminalScoped(): bool
    {
        return match ($this) {
            self::TerminalAdmin, self::TerminalOfficer, self::TerminalSupervisor => true,
            default => false,
        };
    }

    /**
     * Field roles reach only their own assigned tasks (FR-AC-16, DEC-48: no web abilities).
     */
    public function isFieldRole(): bool
    {
        return match ($this) {
            self::MarinePilot, self::TugMaster, self::TerminalSupervisor => true,
            default => false,
        };
    }
}
