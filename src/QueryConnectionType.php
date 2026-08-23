<?php

namespace Tyto\Agent;

enum QueryConnectionType: string
{
    case Read = 'read';
    case Write = 'write';
    case Unknown = 'unknown';
}
