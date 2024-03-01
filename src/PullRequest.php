<?php

namespace GithubBot;

readonly class PullRequest
{
    public function __construct(
        public string $owner,
        public string $repo,
        public string $branch,
        public int $number,
        public string $title,
        public string $body
    ) {
    }

    public static function fromPayload(array $payload): self
    {
        return new self(
            $payload['repository']['owner']['login'],
            $payload['repository']['name'],
            $payload['pull_request']['head']['ref'],
            $payload['pull_request']['number'],
            $payload['pull_request']['title'],
            $payload['pull_request']['body']
        );
    }
}