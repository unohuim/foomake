<?php

namespace App\Actions\Notes;

use App\Models\Note;
use Illuminate\Database\Eloquent\Model;

/**
 * Builds the shared Activity & Notes feed payload for resource detail pages.
 */
class BuildNotesFeedPayloadAction
{
    /**
     * Build the feed contract for a supported noteable resource.
     *
     * @return array<string, mixed>
     */
    public function execute(Model $noteable, string $listUrl, string $storeUrl): array
    {
        $notes = Note::query()
            ->where('tenant_id', (int) $noteable->tenant_id)
            ->where('noteable_type', $noteable::class)
            ->where('noteable_id', $noteable->getKey())
            ->with('author')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return [
            'list_url' => $listUrl,
            'store_url' => $storeUrl,
            'csrf_token' => csrf_token(),
            'empty_state' => 'No notes yet.',
            'notes' => $notes
                ->map(fn (Note $note): array => $this->notePayload($note))
                ->values()
                ->all(),
        ];
    }

    /**
     * Build a note read model.
     *
     * @return array<string, mixed>
     */
    private function notePayload(Note $note): array
    {
        return [
            'id' => $note->id,
            'tenant_id' => $note->tenant_id,
            'noteable_type' => $note->noteable_type,
            'noteable_id' => $note->noteable_id,
            'author_user_id' => $note->author_user_id,
            'author_name' => $note->author?->name ?? 'Unknown user',
            'body' => $note->body,
            'visibility' => $note->visibility,
            'is_pinned' => (bool) $note->is_pinned,
            'created_at' => $note->created_at?->toISOString(),
            'created_at_display' => $note->created_at?->diffForHumans() ?? '',
        ];
    }
}
