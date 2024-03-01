<?php

namespace GithubBot;

class FileDownloader
{
    public function download(string $url): string
    {
        return file_get_contents($url);
    }
}