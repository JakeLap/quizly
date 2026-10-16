<?php

use App\Broadcasting\RoomChannel;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('room.{code}', RoomChannel::class, ['guards' => ['host', 'player']]);
