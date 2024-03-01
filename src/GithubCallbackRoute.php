<?php

namespace GithubBot;

use Github\AuthMethod;
use Github\Client;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Signer\Key\LocalFileReference;
use Lcobucci\JWT\Signer\Rsa\Sha256;

class GithubCallbackRoute
{
    private Client $client;
    private Area $area;

    private const APP_ID = '846121';

    public function __construct()
    {
        $this->client = new Client();
        $this->area = new Area(new FileDownloader());
    }

    public function __invoke(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo 'Method not allowed';
            exit;
        }

        $json = file_get_contents('php://input');

        $data = json_decode($json, true);

        $this->validateRequest($data);

        $this->authenticateToGithub($data['payload']['installation']['id']);

        $pr = PullRequest::fromPayload($data['payload']);

        $ticket = $this->parseTicketNumber($pr->body);

        if ($ticket === null) {
            //create ticket over lambda
        }

        $this->updatePullRequestTitle($pr, $ticket);

        $this->updateChangelog($pr, $ticket);

        $this->labelAreas($pr);

        //$this->addCommentToPR($repoOwner, $repo);
    }

    /**
     * Maybe we should also check pull request body and commit messages
     */
    private function parseTicketNumber(string $body): ?string
    {

        preg_match('/### 4\..*?NEXT-(\d+).*?### 5\./', $body, $matches);

        if (empty($matches)) {
            return null;
        }

        if ((int) $matches[1] === 0) {
            //NEXT-0000 is invalid
            return null;
        }

        return $matches[1];
    }

    private function authenticateToGithub(string $installationId): void
    {
        $config = Configuration::forSymmetricSigner(
            new Sha256(),
            LocalFileReference::file(__DIR__ . '/../shopware-pr-bot.2024-03-01.private-key.pem')
        );

        $now = new \DateTimeImmutable();
        $jwt = $config->builder(ChainedFormatter::withUnixTimestampDates())
            ->issuedBy(static::APP_ID)
            ->issuedAt($now)
            ->expiresAt($now->modify('+10 minutes'))
            ->getToken($config->signer(), $config->signingKey())
        ;

        $this->client->authenticate($jwt->toString(), null, AuthMethod::JWT);
        $response = $this->client->apps()->createInstallationToken($installationId);
        $this->client->authenticate($response['token'], null, AuthMethod::ACCESS_TOKEN);
    }

    private function addCommentToPR(string $owner, string $repo): void
    {
        $this->client->issue()->comments()->create($owner, $repo, 1, [
            'body'      => 'Hello from Shopware',
        ]);
    }

    private function validateRequest(array $data): void
    {
        // check that we have a valid JSON data
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo 'Invalid JSON data 1';
            exit;
        }

        // check that decoded data contains event key and is equal to pull_request
        if (!isset($data['event']) ||
            $data['event'] !== 'pull_request' ||
            !isset($data['payload']) ||
            !isset($data['payload']['action']) ||
            $data['payload']['action'] !== 'unlabeled'
        ) {
            http_response_code(400);
            echo 'Invalid JSON data 2';
            exit;
        }

        // check that sender is a specific username (check that is part of shopware)
        if (!isset($data['payload']['sender']) || $data['payload']['sender']['login'] !== 'AydinHassan') {
            http_response_code(400);
            echo 'Invalid JSON data 3';
            exit;
        }

        // check that label removed was 'Triage required'
        if (!isset($data['payload']['label']) || $data['payload']['label']['name'] !== 'duplicate') {
            http_response_code(400); // Bad request
            echo 'Invalid JSON data 4';
            exit;
        }

        // check that we have installation id
        if (!isset($data['payload']['installation']['id'])) {
            http_response_code(400); // Bad request
            echo 'Invalid JSON data 5';
            exit;
        }
    }

    private function updatePullRequestTitle(PullRequest $pr, ?string $ticket): void
    {
        $title = 'NEXT-' . $ticket . ' - ' . preg_replace('/NEXT-\d+/', '', $pr->title);

        $this->client->pullRequest()->update($pr->owner, $pr->repo, $pr->number, [
            'title' => $title,
        ]);
    }

    private function updateChangelog(PullRequest $pr, ?string $ticket): void
    {
        $files = $this->client->pullRequest()->files($pr->owner, $pr->repo, $pr->number);

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
        $contents = array_filter($contents, fn (string $line) => !str_starts_with($line, 'issue:',));

        array_splice($contents, 1, 0, ['issue: NEXT-' . $ticket]);

        $newContent = implode("\n", $contents);

        $this->client->repo()->contents()->update($pr->owner, $pr->repo, $changelog['filename'], $newContent, 'Update changelog', $changelog['sha'], $pr->branch);
    }

    private function labelAreas(PullRequest $pr): void
    {
        $files = $this->client->pullRequest()->files($pr->owner, $pr->repo, $pr->number);

        $areas = $this->area->decide($files);

        if (!empty($areas->all)) {
            $this->client->issue()->labels()->add($pr->owner, $pr->repo, $pr->number, $areas->all);
        }
    }
}