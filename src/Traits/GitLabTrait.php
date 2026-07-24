<?php

namespace TedbowDrupalScripts\Traits;

use Symfony\Component\HttpClient\HttpClient;
use TedbowDrupalScripts\IssueRef;
use TedbowDrupalScripts\Settings;

trait GitLabTrait
{
    /**
     * Gets the issue fork project (named PROJECT-ISSUENUMBER) for an issue.
     */
    protected function getProjectInfo(string $issue): ?array
    {
        $project_response = $this->getGitLabApiData("https://git.drupalcode.org/api/v4/projects/?search=-$issue");
        if (empty($project_response)) {
            return null;
        }
        \assert(is_array($project_response) && count($project_response) === 1, 'Expected exactly one project to be found.');
        return $project_response[0];
    }

    protected function getProjectId(string $issue): string
    {
        $projectInfo = $this->getProjectInfo($issue);
        if (!$projectInfo) {
            throw new \Exception("No project found for issue $issue");
        }
        \assert(isset($projectInfo['id']), 'Expected project to have an ID.');
        return (string) $projectInfo['id'];
    }

    /**
     * Determines the canonical project path (e.g. "project/canvas").
     *
     * Order: the parsed issue URL, the fork's upstream project, the
     * default_gitlab_project setting.
     */
    protected function getCanonicalProjectPath(?array $forkProjectInfo, ?IssueRef $ref): string
    {
        if ($ref?->projectPath) {
            return $ref->projectPath;
        }
        if ($forkProjectInfo) {
            // The project search response omits forked_from_project; the
            // single-project endpoint includes it.
            if (!isset($forkProjectInfo['forked_from_project'])) {
                $forkProjectInfo = $this->getGitLabApiData("https://git.drupalcode.org/api/v4/projects/{$forkProjectInfo['id']}");
            }
            if (isset($forkProjectInfo['forked_from_project']['path_with_namespace'])) {
                return $forkProjectInfo['forked_from_project']['path_with_namespace'];
            }
        }
        return Settings::getSetting('default_gitlab_project', 'project/canvas');
    }

    protected function getProjectMrs(string $projectId, bool $includeComments = true, ?string $canonicalProject = null): array
    {
        $encodedProject = rawurlencode($canonicalProject ?? 'project/canvas');
        $mr_response = $this->getGitLabApiData("https://git.drupalcode.org/api/v4/projects/$encodedProject/merge_requests?state=all&source_project_id=$projectId");
        return $this->filterMrs($mr_response, $includeComments);
    }

    /**
     * Reduces raw MR API responses to the keys the commands use.
     */
    private function filterMrs(array $mr_response, bool $includeComments): array
    {
        $important_keys = [
            'id',
            'iid',
            'title',
            'description',
            'draft',
            'state',
            'user_notes_count',
            'created_at',
            'merged_at',
            'closed_at',
            'merge_status',
            'detailed_merge_status',
            'author',
            'assignee',
            'web_url',
            'has_conflicts',
            'source_branch',
            'target_branch',
            'blocking_discussions_resolved',
            'created_at',
            'updated_at',
            'work_in_progress',
        ];

        $mrs = [];
        foreach ($mr_response as $mr) {
            $filtered_mr = array_intersect_key($mr, array_flip($important_keys));
            self::filterUserInfo($filtered_mr);
            $filtered_mr['diff_web_url'] = $mr['web_url'] . '.diff';
            if ($includeComments) {
                $filtered_mr['comments'] = $this->geMrComments($mr['target_project_id'], $mr['iid']);
                $filtered_mr['mr_participants'] = implode(',', self::getUsersFromMrComments($filtered_mr['comments']));
            }
            $mrs[] = $filtered_mr;
        }
        return $mrs;
    }

    /**
     * Fetches a GitLab issue. Work items on git.drupalcode.org are served by
     * the /issues REST endpoint; /work_items is the fallback.
     */
    protected function getGitLabIssue(string $host, string $projectPath, string $iid): array
    {
        $encoded = rawurlencode($projectPath);
        try {
            return $this->getGitLabApiData("https://$host/api/v4/projects/$encoded/issues/$iid");
        } catch (\Exception $e) {
            return $this->getGitLabApiData("https://$host/api/v4/projects/$encoded/work_items/$iid");
        }
    }

    protected function getGitLabIssueNotes(string $host, string $projectPath, string $iid): array
    {
        $encoded = rawurlencode($projectPath);
        $notes_response = $this->getGitLabApiData("https://$host/api/v4/projects/$encoded/issues/$iid/notes?sort=asc");
        $notes = [];
        foreach ($notes_response as $note) {
            if (($note['system'] ?? false) === true) {
                continue;
            }
            $note_data = array_intersect_key($note, array_flip(['id', 'body', 'author', 'created_at', 'updated_at']));
            self::filterUserInfo($note_data);
            $notes[$note['id']] = $note_data;
        }
        return $notes;
    }

    protected function getGitLabRelatedMrs(string $host, string $projectPath, string $iid, bool $includeComments = true): array
    {
        $encoded = rawurlencode($projectPath);
        $mr_response = $this->getGitLabApiData("https://$host/api/v4/projects/$encoded/issues/$iid/related_merge_requests");
        return $this->filterMrs($mr_response, $includeComments);
    }

    private static function filterUserInfo(array &$data): void
    {
        foreach ($data as &$item) {
            if (is_array($item) && isset($item['username']) && isset($item['name']) && isset($item['public_email'])) {
                $item = $item['username'];
            }
        }
    }

    private static function getUsersFromMrComments(array $comments): array
    {
        $users = [];
        foreach ($comments as $comment) {
            $users[] = $comment['author'];
        }
        return array_unique($users);
    }

    protected function geMrComments(string $projectId, string $iid): array
    {
        $url = "https://git.drupalcode.org/api/v4/projects/$projectId/merge_requests/$iid/notes";
        $comments_response = $this->getGitLabApiData($url);
        $comments = [];
        $keep_keys = [
            'id',
            'body',
            'author',
            'created_at',
            'updated_at',
            'position',
            'resolved',
            'resolved_by',
            'resolved_at',
            'suggestions',
        ];
        foreach ($comments_response as &$comment) {
            if ($comment['system'] === true) {
                continue;
            }
            $comments[$comment['id']] = array_intersect_key($comment, array_flip($keep_keys));
            self::filterUserInfo($comments[$comment['id']]);
            if (isset($comments[$comment['id']]['position'])) {
                unset($comments[$comment['id']]['position']['base_sha'], $comments[$comment['id']]['position']['start_sha'], $comments[$comment['id']]['position']['head_sha']);
            }
        }
        return $comments;
    }

    /**
     * Fetch data from GitLab API with proper headers and authentication.
     *
     * @param string $url The GitLab API URL
     * @return array The decoded JSON response
     * @throws \Exception If the request fails
     */
    protected function getGitLabApiData(string $url, ?string $page = null): array
    {
        static $httpClient = null;

        if ($httpClient === null) {
            $httpClient = HttpClient::create();
        }

        $headers = [
            'User-Agent' => 'DrupalScripts/1.0 (Symfony HttpClient)',
            'Accept' => 'application/json',
        ];
        if ($page) {
            $pagedUrl = $url . (str_contains($url, '?') ? "&page=$page" : "?page=$page");
        } else {
            $pagedUrl = $url;
        }

        // Add GitLab token if available
        $token = Settings::getSetting('gitlab_token', null);
        if ($token) {
            $headers['Authorization'] = 'Bearer ' . $token;
            // Alternative: $headers['Private-Token'] = $token;
        }

        try {
            $response = $httpClient->request('GET', $pagedUrl, [
                'headers' => $headers,
                'timeout' => 30,
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new \Exception(sprintf(
                    'GitLab API request failed with status %d: %s',
                    $response->getStatusCode(),
                    $response->getContent(false)
                ));
            }
            // Check for the `x-next-page` header and request the recursive pages if needed.
            $nextPage = $response->getHeaders(false)['x-next-page'][0] ?? null;
            $return = $response->toArray();
            if ($nextPage) {
                $return = array_merge($this->getGitLabApiData($url, $nextPage), $return);
            }

            return $return;
        } catch (\Exception $e) {
            throw new \Exception('Failed to fetch GitLab API data: ' . $e->getMessage());
        }
    }
}
