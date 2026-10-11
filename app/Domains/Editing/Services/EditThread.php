<?php

namespace App\Domains\Editing\Services;

use App\Domains\Editing\Models\ImageEdit;
use App\Models\User;

/**
 * The conversation behind an edit.
 *
 * An edit whose source is a generation or a product is a root; an edit whose
 * source is another edit is a reply. Walking the source links therefore
 * recovers the whole thread from any of its steps, with no thread table and
 * no column that could drift out of step with the data it describes.
 *
 * Cycles cannot form - source_type and source_id are written once at creation
 * and never rewritten - but the walk is bounded anyway, because an unbounded
 * loop over rows a user can create is not a shape worth keeping.
 */
class EditThread
{
    /**
     * The conversation an edit belongs to, oldest first.
     *
     * Ancestors are found by walking up the source links and descendants by
     * walking down them one level at a time, so opening any step - including
     * the root the user started from - shows the whole thread.
     *
     * @return list<array{id: int, operation: string, status: string, message: ?string, take: ?string, output_media_id: ?int, created_at: mixed}>
     */
    public function thread(ImageEdit $edit, User $user): array
    {
        $ancestors = $this->ancestors($edit, $user);
        /** @var ImageEdit $root */
        $root = end($ancestors);

        $steps = [$root->id => $root];

        // One query per level down. Each level is one edit deeper, and a
        // legitimate conversation cannot exceed the same cap that creation
        // enforces, so this loop is bounded by data it could not have made.
        $levels = (int) config('ai.max_edit_chain');
        while ($levels-- > 0) {
            $replies = $user->imageEdits()
                ->where('source_type', 'edit')
                ->whereIn('source_id', array_keys($steps))
                ->whereNotIn('id', array_keys($steps))
                ->get();

            if ($replies->isEmpty()) {
                break;
            }

            foreach ($replies as $reply) {
                $steps[$reply->id] = $reply;
            }
        }

        return array_map(
            fn (ImageEdit $step): array => $this->step($step),
            collect($steps)->sortBy('id')->values()->all(),
        );
    }

    /**
     * How many edits this one sits behind, counting itself: a root is depth 1.
     */
    public function depth(ImageEdit $edit, User $user): int
    {
        return count($this->ancestors($edit, $user));
    }

    /**
     * @return array{id: int, operation: string, status: string, message: ?string, take: ?string, output_media_id: ?int, created_at: mixed}
     */
    private function step(ImageEdit $step): array
    {
        $takeIndex = $step->options['take_index'] ?? null;

        return [
            'id' => $step->id,
            'operation' => $step->operation,
            'status' => $step->status,
            'message' => $step->message,
            'take' => $takeIndex === null ? null : (EditPromptBuilder::TAKES[(int) $takeIndex]['key'] ?? null),
            'output_media_id' => $step->output_media_id,
            'created_at' => $step->created_at,
        ];
    }

    /**
     * @return list<ImageEdit> the edit itself and every ancestor, nearest first
     */
    private function ancestors(ImageEdit $edit, User $user): array
    {
        $chain = [$edit];
        $cursor = $edit;

        while ($cursor->source_type === 'edit' && count($chain) < (int) config('ai.max_edit_chain')) {
            $next = $user->imageEdits()->find((int) $cursor->source_id);
            if ($next === null) {
                break;
            }

            $chain[] = $next;
            $cursor = $next;
        }

        return $chain;
    }
}
