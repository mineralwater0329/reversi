<?php

namespace App\Events;

use App\Http\Logic\LivewireLogic;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PassEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($start_time)
    {
        $Logic = new LivewireLogic;
        $user = auth()->user();
        $time = $Logic->diff_time($start_time, $user->time);
        $user->time = $time;
        $user->save();
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        $room_id = auth()->user()->room_id;
        return new PrivateChannel('battle.'.$room_id);
    }
    public function broadcastWith() {
        return [
            'message' => '相手がパスしました',
        ];
    }
}