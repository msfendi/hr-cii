<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ContractExpiringEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $notification;
    public $contractData;

    /**
     * Create a new event instance.
     */
    public function __construct($notification)
    {
        $this->notification = $notification;
        
        // Prepare data untuk broadcast
        $this->contractData = [
            'id' => $notification->id,
            'contract_id' => $notification->contract_id,
            'npk' => $notification->npk,
            'employee_name' => $notification->employee_name,
            'contract_end_date' => $notification->contract_end_date->format('Y-m-d'),
            'days_remaining' => $notification->days_remaining,
            'type' => $notification->type,
            'formatted_date' => $notification->created_at->format('d M Y H:i'),
            'end_date_formatted' => $notification->contract_end_date->format('d M Y'),
        ];
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('hr.contract-notifications'),
        ];
    }

    public function broadcastAs()
    {
        return 'ContractExpiring';
    }

    public function broadcastWith()
    {
        return [
            'data' => $this->contractData,
            'message' => "Kontrak {$this->contractData['employee_name']} akan habis dalam {$this->contractData['days_remaining']} hari",
        ];
    }
}
