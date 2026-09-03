<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

#[Fillable(['ticket_id', 'image_path'])]
class TicketImage extends Model
{
    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function getImageUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->image_path);
    }

    public function deleteWithFile(): void
    {
        Storage::disk('public')->delete($this->image_path);

        $this->delete();
    }
}
