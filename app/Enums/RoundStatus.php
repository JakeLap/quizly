<?php

namespace App\Enums;

enum RoundStatus: string
{
    /** Created, but the question has not been shown yet. */
    case Pending = 'pending';

    /** The question is on screen; answers unlock at `opens_at`. */
    case Open = 'open';

    /** The deadline passed or everyone answered; answers are no longer accepted. */
    case Closed = 'closed';

    /** The correct answer and the points have been shown. */
    case Revealed = 'revealed';
}
