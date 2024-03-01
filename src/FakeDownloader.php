<?php

namespace GithubBot;

class FakeDownloader extends FileDownloader
{
    public function __construct(private array $files = [])
    {
    }

    public function download(string $url): string
    {
        return $this->files[$url];
    }
}