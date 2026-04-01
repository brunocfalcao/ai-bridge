<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Ai;

enum AiScope: string
{
    case LeadsDiscover = 'leads-discover';
    case LeadsOsint = 'leads-osint';
    case LeadsDispatch = 'leads-dispatch';
    case Wizard = 'wizard';
    case StudyPreview = 'study-preview';
    case StudyFull = 'study-full';
    case StudyCe = 'study-ce';
}
