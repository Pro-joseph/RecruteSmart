<?php

declare(strict_types=1);

namespace App\Enums;

enum FieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Email = 'email';
    case Phone = 'phone';
    case Url = 'url';
    case Date = 'date';
    case Select = 'select';
    case Multiselect = 'multiselect';
    case Boolean = 'boolean';
    case File = 'file';
    case Image = 'image';
}
