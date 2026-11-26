<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class SatisfactionSubmissionGrouper
{
    private function flattenResponses(Collection $responses): array
    {
        $rows = [];
        foreach ($responses as $item) {
            if (is_array($item) && array_key_exists('answers', $item)) {
                foreach ($item['answers'] as $answer) {
                    $rows[] = $answer;
                }
                continue;
            }
            $rows[] = $item;
        }
        return $rows;
    }

    /**
     * Group responses by explicit submission ID. Rows created before the ID
     * was introduced retain the historical two-second fallback.
     */
    public function group(Collection $responses): Collection
    {
        $rows = $this->flattenResponses($responses);
        $groups = [];
        $legacyKey = null;
        $legacyAnchor = null;

        foreach ($rows as $response) {
            $submissionId = trim((string) ($response->submission_id ?? ''));
            $createdAt = Carbon::parse($response->created_at);

            if ($submissionId !== '') {
                $key = 'submission:' . $submissionId;
                if (!isset($groups[$key])) {
                    $groups[$key] = ['waktu' => $createdAt, 'answers' => []];
                }
                $groups[$key]['answers'][] = $response;
                continue;
            }

            if ($legacyAnchor === null || $createdAt->diffInSeconds($legacyAnchor) > 2) {
                $legacyAnchor = $createdAt;
                $legacyKey = 'legacy:' . (string) ($response->id ?? count($groups));
                $groups[$legacyKey] = ['waktu' => $createdAt, 'answers' => []];
            }

            $groups[$legacyKey]['answers'][] = $response;
        }

        return collect(array_values($groups))
            ->sortBy(fn (array $group) => $group['waktu']->getTimestamp())
            ->values();
    }
}
