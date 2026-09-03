<?php

namespace App;

enum SettingValueType: string
{
    case String = 'string';
    case Integer = 'integer';
    case Boolean = 'boolean';
    case Select = 'select';
}
