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
}
