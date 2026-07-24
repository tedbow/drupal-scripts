<?php


namespace TedbowDrupalScripts\Command;

use League\HTMLToMarkdown\HtmlConverter;
use Neckberg\Hashdown\Hashdown;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TedbowDrupalScripts\IssueRef;
use TedbowDrupalScripts\Settings;
use TedbowDrupalScripts\Traits\GitLabTrait;

class IssueInfo extends CommandBase
{
    use GitLabTrait;

    protected const REQUIRE_CLEAN_GIT = false;
    protected static $defaultName = "issue:info";
    protected static $requireAtRoot = false;

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        parent::configure();
        $this->setDescription('Get basic issue info');
        $this->setAliases(['info']);
        $this->addArgument('issue_number', InputArgument::OPTIONAL, 'The issue number or issue URL (drupal.org or GitLab)');
        $this->addOption('comments');
        $this->addOption('mrs');
    }


    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (self::FAILURE === parent::execute($input, $output)) {
            return self::FAILURE;
        }
        $ref = $this->resolveIssueRef($input->getArgument('issue_number'));
        if ($ref && $ref->number === null) {
            $this->style->error('This command needs an issue number or issue URL, not an MR URL.');
            return self::FAILURE;
        }
        if ($ref && $this->isGitLabIssue($ref)) {
            return $this->executeGitLab($ref, $input, $output);
        }
        $issue = $ref?->number;

        if ($issue) {
            $node_info = $this->getEntityInfo($issue);

            $node_info = (array) $node_info;
            // Need field_issue_version, author, flag_tracker_follower_count?
            $important_keys = ['title', 'field_issue_component', 'body', 'comment_count', 'field_issue_category', 'field_issue_status', 'taxonomy_vocabulary_9', 'field_issue_related_links'];
            $important = array_intersect_key($node_info, array_flip($important_keys));
            $important['last updated'] = $this->getTimeFromTimeStamp($node_info['field_issue_last_status_change']);
            $important['summary'] = $important['body']->value;
            $important['field_issue_related_links'] = array_map(function ($link) {
                return $link->url;
            }, $important['field_issue_related_links']);
            unset($important['body']);
            $this->setIssueStatus($important);
            $this->setIssueCategory($important);
            //$important['created by'] = $node_info['author'];
            $last_comment = (array) $this->getEntityInfo($node_info['comments'][$important['comment_count'] - 1]->id, 'comment');
            unset($important['comment_count']);
            $important_keys = ['name'];
            $important_last_comment = array_intersect_key($last_comment, array_flip($important_keys));
            $important_last_comment['created'] =$this->getTimeFromTimeStamp($last_comment['created']);
            $this->setTags($important);
            if ($input->getOption('comments')) {
                $important['comments'] = $this->getIssueComments($issue);
            }
            if ($input->getOption('mrs')) {
                $forkInfo = $this->getProjectInfo($issue, $ref->projectPath ? basename($ref->projectPath) : null);
                $important['merge requests'] = $forkInfo
                    ? $this->getProjectMrs((string) $forkInfo['id'], true, $this->getCanonicalProjectPath($forkInfo, $ref))
                    : [];
            }

            $important['last comment'] = $important_last_comment;
            $my_uid = Settings::getSetting('my_user_id');
            if ($last_comment['author']->id != $my_uid) {
                // Get my last comment
                $comments = (array) json_decode(file_get_contents("https://www.drupal.org/api-d7/comment.json?node={$node_info['nid']}&author=$my_uid"))->list;
                $important['my comment count'] = count($comments);
                $comment = array_pop($comments);
                if ($comment) {
                    $important['my last comment'] = $this->getTimeFromTimeStamp($comment->created);
                }
                //$important['my last comment'] = $important_last_comment['created'] = date("Y-m-d H:i:s", $comment->created);
            }
            if ($input->getOption('format') === 'md') {
                $this->outputMarkdown($important, $output);
                return self::SUCCESS;
            }
            if ($this->outputJson($input)) {
                $output->write(json_encode($important, JSON_PRETTY_PRINT));
                return self::SUCCESS;
            }
            $output->writeln("⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐");
            $output->write(print_r($important, true));
            $output->writeln("⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐");
            return self::SUCCESS;
        } else {
            $this->style->warning('Could not find issue for branch');
            return self::FAILURE;
        }
    }

    /**
     * Decides whether an issue lives on GitLab rather than Drupal.org.
     *
     * Bare numbers are probed against the d.o API: GitLab-native issues have
     * no d.o node. A successful probe is cached by getEntityInfo() so the
     * d.o path does not refetch.
     */
    private function isGitLabIssue(IssueRef $ref): bool
    {
        if ($ref->source === IssueRef::SOURCE_GITLAB) {
            return true;
        }
        if ($ref->source === IssueRef::SOURCE_DO) {
            return false;
        }
        try {
            // The number spaces can collide: a d.o node with this id may exist
            // but be something other than an issue (e.g. a release node).
            $node = $this->getEntityInfo($ref->number);
            return ($node->type ?? '') !== 'project_issue';
        } catch (\Throwable $e) {
            return true;
        }
    }

    private function executeGitLab(IssueRef $ref, InputInterface $input, OutputInterface $output)
    {
        $host = $ref->host ?? 'git.drupalcode.org';
        $projectPath = $ref->projectPath;
        if (!$projectPath) {
            // Bare number: derive the canonical project from the issue fork.
            $projectPath = $this->getCanonicalProjectPath($this->getProjectInfo($ref->number, $ref->projectPath ? basename($ref->projectPath) : null), $ref);
        }
        $issue_info = $this->getGitLabIssue($host, $projectPath, $ref->number);

        $important = [
            'title' => $issue_info['title'],
            'status' => $issue_info['state'],
            'tags' => $issue_info['labels'] ?? [],
            'last updated' => $issue_info['updated_at'],
            'summary' => $issue_info['description'] ?? '',
        ];

        $notes = $this->getGitLabIssueNotes($host, $projectPath, $ref->number);
        if ($input->getOption('comments')) {
            $important['comments'] = array_map(function ($note) {
                return [
                    'author' => is_array($note['author']) ? ($note['author']['username'] ?? '') : $note['author'],
                    'comment' => $note['body'],
                    'created' => $note['created_at'],
                ];
            }, array_values($notes));
        }
        if ($input->getOption('mrs')) {
            $important['merge requests'] = $this->getGitLabRelatedMrs($host, $projectPath, $ref->number);
        }
        if ($notes) {
            $last_note = end($notes);
            $important['last comment'] = [
                'name' => is_array($last_note['author']) ? ($last_note['author']['username'] ?? '') : $last_note['author'],
                'created' => $last_note['created_at'],
            ];
        }

        if ($input->getOption('format') === 'md') {
            $this->outputMarkdown($important, $output, false);
            return self::SUCCESS;
        }
        if ($this->outputJson($input)) {
            $output->write(json_encode($important, JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }
        $output->write(print_r($important, true));
        return self::SUCCESS;
    }

    private function setTags(array &$data): void
    {
        \assert(isset($data['taxonomy_vocabulary_9']));
        $tags = [];
        foreach ($data['taxonomy_vocabulary_9'] as $tagInfo) {
            \assert(isset($tagInfo->id));
            $info = $this->getEntityInfo($tagInfo->id, 'taxonomy_term');
            $tags[$tagInfo->id] = $info->name;
        }
        $data['tags'] = $tags;
        unset($data['taxonomy_vocabulary_9']);
    }

    private function getIssueComments(mixed $issue): array
    {
        $comments_response = (array) json_decode(file_get_contents("https://www.drupal.org/api-d7/comment.json?node=$issue"));
        $comments = [];
        do {
            foreach ($comments_response['list'] as $comment) {
                //var_dump($comment);
                //exit();
                if ($comment->name === "System Message" || ($comment->comment_body->value ?? '') === '') {
                    continue;
                }
                $comments[$comment->cid] = [
                    'author' => $comment->name,
                    'comment' => $comment->comment_body->value ?? '',
                    'created' => $this->getTimeFromTimeStamp($comment->created),
                ];
            }
            if (isset($comments_response['next'])) {
                $nextUrl = $comments_response['next'];
                $nextUrl = str_replace('comment?', 'comment.json?', $nextUrl);
                $comments_response = (array) json_decode(file_get_contents($nextUrl));
            } else {
                $comments_response = null;
            }
        } while ($comments_response);

        return $comments;
    }

    protected function outputMarkdown(array $data, OutputInterface $output, bool $isHtml = true)
    {
        // GitLab bodies are already markdown; d.o bodies are HTML.
        if ($isHtml) {
            $htmlConverter = new HtmlConverter();
            $md_summary = $htmlConverter->convert($data['summary']);
        } else {
            $md_summary = $data['summary'];
        }
        $data['summary'] = 'SUMMARY TOKEN';
        $orig_comments = $data['comments'] ?? [];
        $data['comments'] = [];

        foreach ($orig_comments as $comment) {
            $data['comments']["{$comment['author']} ({$comment['created']})"] = $isHtml ? strip_tags($comment['comment']) : $comment['comment'];
        }
        $orig_mrs = $data['merge requests'] ?? [];
        $data['merge requests'] = [];
        foreach ($orig_mrs as $mr) {
            $new_mr = [
                'URL' => $mr['web_url'],
                'Created at' => $mr['created_at'],
                'Updated at' => $mr['updated_at'],
                'Author' => $mr['author'],
            ];
            $mr['comments'] = $mr['comments'] ?? [];
            $new_mr['Comment Threads'] = [];
            foreach ($mr['comments'] as $comment) {
                $thread_id = $comment['position']['new_line'] ?? 'general';
                if (!isset($new_mr['Comment Threads'][$thread_id])) {
                    $new_mr['Comment Threads'][$thread_id] = [];
                }
                $new_mr['Comment Threads'][$thread_id][] = "{$comment['author']} ({$comment['created_at']}): " . str_replace(['\n', '```'], '', strip_tags($comment['body']));
            }
            $new_mr['Comment Threads'] = array_values($new_mr['Comment Threads']);
            $data['merge requests']["{$mr['title']}: ({$mr['state']}"] = $new_mr;
        }
        $md = Hashdown::s_stringify_x($data, false,TRUE);
        $md = str_replace('SUMMARY TOKEN', $md_summary, $md);
        $output->write($md);
        return self::SUCCESS;
    }


}
