<?php

namespace TedbowDrupalScripts\Traits;

use Symfony\Component\HttpClient\HttpClient;
use TedbowDrupalScripts\Settings;

trait GitLabTrait
{
    protected function getProjectId(string $issue): string
    {
        // First find the project associated with the issue.
        $project_response = $this->getGitLabApiData("https://git.drupalcode.org/api/v4/projects/?search=-$issue");
        if (empty($project_response)) {
            $this->style->error("No project found for issue $issue");
            return self::FAILURE;
        }
        \assert(is_array($project_response) && count($project_response) === 1, 'Expected exactly one project to be found.');
        \assert(isset($project_response[0]['id']), 'Expected project to have an ID.');
        return $project_response[0]['id'];
    }

    protected function getProjectMrs(string $projectId, bool $includeComments = true): array
    {
        $mr_response = $this->getGitLabApiData("https://git.drupalcode.org/api/v4/projects/project%2Fcanvas/merge_requests?state=all&source_project_id=$projectId");
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
            $filtered_mr['diff_web_url'] = $mr['web_url'] . '.diff';
            if ($includeComments) {
                $filtered_mr['comments'] = $this->geMrComments($mr['target_project_id'], $mr['iid']);
                $filtered_mr['mr_participants'] = implode(',', self::getUsersFromMrComments($filtered_mr['comments']));
            }
            $mrs[] = $filtered_mr;
        }
        return $mrs;
    }

    private static function getUsersFromMrComments(array $comments): array
    {
        $users = [];
        foreach ($comments as $comment) {
            $author = $comment['author'];
            $users[] = $author['username'];
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
            'resolvable',
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
    protected function getGitLabApiData(string $url, ?string $page = NULL): array
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
        }
        else {
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
