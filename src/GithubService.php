<?php

namespace GithubBot;

use Github\AuthMethod;
use Github\Client as GithubClient;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Signer\Key\LocalFileReference;
use Lcobucci\JWT\Signer\Rsa\Sha256;

class GithubService
{
    private GithubClient $githubClient;

    private string $appId;
    private string $pemFile;

    public function __construct()
    {
        $this->githubClient = new GithubClient();

        $this->appId = env('GITHUB_APP_ID');
        $this->pemFile = __DIR__ . '/../' . env('GITHUB_PEM_FILE');
    }

    public function authenticateToGithub(string $installationId): void
    {
        $config = Configuration::forSymmetricSigner(
            new Sha256(),
            LocalFileReference::file($this->pemFile)
        );

        $now = new \DateTimeImmutable();
        $jwt = $config->builder(ChainedFormatter::withUnixTimestampDates())
            ->issuedBy($this->appId)
            ->issuedAt($now)
            ->expiresAt($now->modify('+10 minutes'))
            ->getToken($config->signer(), $config->signingKey())
        ;

        $this->githubClient->authenticate($jwt->toString(), null, AuthMethod::JWT);
        $response = $this->githubClient->apps()->createInstallationToken($installationId);
        $this->githubClient->authenticate($response['token'], null, AuthMethod::ACCESS_TOKEN);
    }

    public function updatePullRequestTitle(PullRequest $pr, ?string $ticket): void
    {
        $title = 'NEXT-' . $ticket . ' - ' . preg_replace('/NEXT-\d+/', '', $pr->title);

        $this->githubClient->pullRequest()->update($pr->owner, $pr->repo, $pr->number, [
            'title' => $title,
        ]);

        $pr->title = $title;
    }

    public function updateChangelog(PullRequest $pr, ?string $ticket): void
    {
        $files = $this->githubClient->pullRequest()->files($pr->owner, $pr->repo, $pr->number);

        $changelog = null;

        foreach ($files as $file) {
            if (preg_match('/changelog\/_unreleased\/\d{4}-\d{2}-\d{2}-[A-Za-z0-9-]+.md/', $file['filename'])) {
                $changelog = $file;
                break;
            }
        }

        if ($changelog === null) {
            return;
        }

        $contents = explode("\n", file_get_contents($changelog['raw_url']));
        $contents = array_filter($contents, fn (string $line) => !str_starts_with($line, 'issue:'));

        array_splice($contents, 1, 0, ['issue: NEXT-' . $ticket]);

        $newContent = implode("\n", $contents);

        $this->githubClient->repo()->contents()->update(
            $pr->owner,
            $pr->repo,
            $changelog['filename'],
            $newContent,
            'Update changelog',
            $changelog['sha'],
            $pr->branch
        );
    }

    public function getAreaLabel(PullRequest $pr): ?string
    {
        $labels = $this->githubClient->issue()->labels()->all($pr->owner, $pr->repo, $pr->number);

        foreach ($labels as $label) {
            if (str_starts_with($label['name'], 'Area:')) {
                return $label['name'];
            }
        }

        return null;
    }

    public function getPrFiles(PullRequest $pr): array
    {
        return $this->githubClient->pullRequest()->files($pr->owner, $pr->repo, $pr->number);
    }

    public function addPrLabels(PullRequest $pr, array $labels): void
    {
        $this->githubClient->issue()->labels()->add($pr->owner, $pr->repo, $pr->number, $labels);
    }

    public function getFirstCommit(PullRequest $pr): ?array
    {
        $commits = $this->githubClient->pullRequest()->commits($pr->owner, $pr->repo, $pr->number);

        if (empty($commits)) {
            return null;
        }

        return $commits[0];
    }
}
