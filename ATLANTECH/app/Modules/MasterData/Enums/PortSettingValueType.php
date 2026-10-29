<?php

namespace App\Modules\MasterData\Enums;

/**
 * `port_settings.value_type`.
 */
enum PortSettingValueType: string
{
    case Int = 'INT';
    case Decimal = 'DECIMAL';
    case Bool = 'BOOL';
    case String = 'STRING';
    case Json = 'JSON';
}
