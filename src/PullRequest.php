<?php

namespace GithubBot;

class PullRequest
{
    public function __construct(
        public string $owner,
        public string $repo,
        public string $headRepoCloneUrl,
        public string $branch,
        public string $targetBranch,
        public int $number,
        public string $link,
        public string $title,
        public string $body,
        public string $author,
    ) {
    }

    public static function fromPayload(array $payload): self
    {
        return new self(
            $payload['repository']['owner']['login'],
            $payload['repository']['name'],
            $payload['pull_request']['head']['repo']['ssh_url'],
            $payload['pull_request']['head']['ref'],
            $payload['pull_request']['base']['ref'],
            $payload['pull_request']['number'],
            $payload['pull_request']['html_url'],
            $payload['pull_request']['title'],
            $payload['pull_request']['body'],
            $payload['pull_request']['user']['login'],
        );
    }
}