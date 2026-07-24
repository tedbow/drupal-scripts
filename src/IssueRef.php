<?php

namespace TedbowDrupalScripts;

/**
 * An issue reference parsed from a bare number, an issue URL, or an MR URL.
 *
 * Supported forms:
 *  - 3591785
 *  - https://www.drupal.org/project/canvas/issues/3591785
 *  - https://drupal.org/i/3591785
 *  - https://git.drupalcode.org/project/canvas/-/work_items/3591785
 *  - https://git.drupalcode.org/project/canvas/-/issues/3591785
 *  - https://git.drupalcode.org/project/canvas/-/merge_requests/68
 */
final class IssueRef
{
    public const SOURCE_DO = 'do';
    public const SOURCE_GITLAB = 'gitlab';
    // A bare number could be either source; drupalcode work items share the
    // d.o node-id sequence.
    public const SOURCE_UNKNOWN = 'unknown';

    public function __construct(
        // Null when the reference is an MR URL: MRs carry no issue number.
        public readonly ?string $number,
        public readonly string $source,
        public readonly ?string $projectPath = null,
        public readonly ?string $host = null,
        public readonly ?string $mrIid = null,
    ) {
    }

    /**
     * @throws \InvalidArgumentException
     *   When the reference is not a number or a recognized issue URL.
     */
    public static function parse(string $ref): self
    {
        $ref = trim($ref);
        if (preg_match('/^[0-9]+$/', $ref)) {
            return new self($ref, self::SOURCE_UNKNOWN);
        }
        $clean = rtrim(preg_replace('/[?#].*$/', '', $ref), '/');
        if (preg_match('~^https?://(?:www\.)?drupal\.org/project/([^/]+)/issues/([0-9]+)$~', $clean, $matches)) {
            // The d.o project machine name doubles as the drupalcode namespace.
            return new self($matches[2], self::SOURCE_DO, 'project/' . $matches[1]);
        }
        if (preg_match('~^https?://(?:www\.)?drupal\.org/i/([0-9]+)$~', $clean, $matches)) {
            return new self($matches[1], self::SOURCE_DO);
        }
        // MR URLs live under the canonical project path (not the issue fork),
        // so projectPath here is what the merge_requests API endpoint needs.
        if (preg_match('~^https?://([^/]+)/(.+)/-/merge_requests/([0-9]+)(?:/.*)?$~', $clean, $matches)) {
            return new self(null, self::SOURCE_GITLAB, $matches[2], $matches[1], $matches[3]);
        }
        if (preg_match('~^https?://([^/]+)/(.+)/-/(?:work_items|issues)/([0-9]+)$~', $clean, $matches)) {
            return new self($matches[3], self::SOURCE_GITLAB, $matches[2], $matches[1]);
        }
        throw new \InvalidArgumentException("Unrecognized issue reference: $ref");
    }

    public function encodedProjectPath(): ?string
    {
        return $this->projectPath === null ? null : rawurlencode($this->projectPath);
    }
}
