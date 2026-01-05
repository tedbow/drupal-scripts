<?php


namespace TedbowDrupalScripts\Command;

use Neckberg\Hashdown\Hashdown;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
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
        $this->addArgument('issue_number', InputArgument::OPTIONAL, 'The issue number');
        $this->addOption('comments');
        $this->addOption('mrs');
    }


    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (self::FAILURE === parent::execute($input, $output)) {
            return self::FAILURE;
        }
        $issue = $input->getArgument('issue_number') ?? $this->getBranchIssue();

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
                $important['merge requests'] = $this->getProjectMrs($this->getProjectId($issue));
            }

            $important['last comment'] = $important_last_comment;
            $my_uid = Settings::getSetting('my_user_id');
            if ($last_comment['author']->id != $my_uid) {
                // Get my last comment
                $comments = (array) json_decode(file_get_contents("https://www.drupal.org/api-d7/comment.json?node={$node_info['nid']}&author=$my_uid"))->list;
                $important['my comment count'] = count($comments);
                $comment = array_pop($comments);
                $important['my last comment'] = $this->getTimeFromTimeStamp($comment->created);
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

    protected function outputMarkdown(array $data, OutputInterface $output)
    {
        $orig_comments = $data['comments'] ?? [];
        $data['comments'] = [];

        foreach ($orig_comments as $comment) {
            $data['comments']["{$comment['author']} ({$comment['created']})"] = strip_tags($comment['comment']);
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
            $new_mr['Comments'] = [];
            foreach ($mr['comments'] as $comment) {
                $new_mr['Comments'][] = "{$comment['author']} ({$comment['created_at']}): " . str_replace(['\n', '```'], '', strip_tags($comment['body']));
            }
            $data['merge requests']["{$mr['title']}: ({$mr['state']}"] = $new_mr;
        }
        parent::outputMarkdown($data, $output);
    }


}
