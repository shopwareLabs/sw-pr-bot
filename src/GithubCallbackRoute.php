<?php

namespace GithubBot;

class GithubCallbackRoute
{
    private GithubService $githubService;
    private GitlabService $gitlabService;
    private JiraService $jiraService;
    private Area $area;

    private string $githubOrg;
    private string $action;
    private string $label;

    public function __construct()
    {
        $this->githubService = new GithubService();
        $this->gitlabService = new GitlabService();
        $this->jiraService = new JiraService();
        $this->area = new Area(new FileDownloader());

        $this->githubOrg = env('GITHUB_ORG');
        $this->action = env('ACTION');
        $this->label = env('LABEL');
    }

    public function __invoke(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo 'Method not allowed';
            exit;
        }

        try {
            $this->handle();
        } catch (\Throwable $e) {
            http_response_code(500);
            echo 'Internal server error';

            error_log($e->getMessage());

            $logFile = fopen(__DIR__ . '/../logs/github-callback.log', 'a');

            fwrite($logFile, date('Y-m-d H:i:s') . ' ERROR ' . $e->getMessage() . PHP_EOL . $e->getTraceAsString() . PHP_EOL);
            fclose($logFile);

            exit;
        }
    }

    private function handle(): void
    {
        $json = file_get_contents('php://input');

        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $this->validateRequest($data);

        $this->githubService->authenticateToGithub($data['payload']['installation']['id']);

        $pr = PullRequest::fromPayload($data['payload']);

        $firstCommit = $this->githubService->getFirstCommit($pr);

        if ($firstCommit === null) {
            http_response_code(400);
            echo 'No commits found';
            exit;
        }

        $areas = $this->labelAreas($pr);

        $ticket = $this->jiraService->parseTicketNumber($pr->body);

        if ($ticket === null) {
            $ticket = $this->jiraService->createTicket($pr, $areas->mostLikelyCandidate);
        }

        $this->githubService->updatePullRequestTitle($pr, $ticket);

        $this->githubService->updateChangelog($pr, $ticket);

        $this->gitlabService->createGitlabMR($pr, $ticket, $firstCommit);

        $this->githubService->removeLabel($pr, $this->label);
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
            $data['payload']['action'] !== $this->action
        ) {
            http_response_code(400);
            echo 'Invalid JSON data 2';
            exit;
        }

        // check that sender is part of shopware
        if (!isset($data['payload']['sender']) || !isset($data['payload']['sender']['login'])) {
            http_response_code(400);
            echo 'Invalid JSON data 3';
            exit;
        }

        $sender = $data['payload']['sender']['login'];

        $this->githubService->isUserPartOfOrganisation($this->githubOrg, $sender);

        // check that label removed was 'Triage required'
        if (!isset($data['payload']['label']) || $data['payload']['label']['name'] !== $this->label) {
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

    private function labelAreas(PullRequest $pr): AreaList
    {
        $areaLabel = $this->githubService->getAreaLabel($pr);

        if ($areaLabel !== null) {
            return new AreaList([$areaLabel], $areaLabel);
        }

        $files = $this->githubService->getPrFiles($pr);

        $areas = $this->area->decide($files);

        $this->githubService->addPrLabels($pr, $areas->all);

        return $areas;
    }
}
