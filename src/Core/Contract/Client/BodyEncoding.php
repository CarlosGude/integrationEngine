<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract\Client;

enum BodyEncoding: string
{
    case Json = 'json';
    case Form = 'form';
}
