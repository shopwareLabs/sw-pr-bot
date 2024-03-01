<?php

namespace GithubBot;

class AreaList
{
    public function __construct(public array $all, public string $mostLikelyCandidate)
    {
        if (!in_array($mostLikelyCandidate, $all, true)) {
            throw new \InvalidArgumentException('Most likely candidate must be in the list of areas');
        }
    }
}