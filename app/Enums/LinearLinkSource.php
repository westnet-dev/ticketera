<?php

namespace App\Enums;

enum LinearLinkSource: string
{
    /** An admin linked the issue from the ticket. */
    case Manual = 'manual';

    /** The Linear issue has the ticket's URL as a link attachment. */
    case Attachment = 'attachment';
}
