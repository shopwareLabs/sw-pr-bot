<?php

namespace GithubBot;

use Gitlab\Client as GitlabClient;

class GitlabService
{
    private GitlabClient $gitlabClient;

    private string $repoUrl;
    private string $token;
    private string $projectId;

    public function __construct()
    {
        $this->gitlabClient = new GitlabClient();

        $this->gitlabClient->setUrl(env('GITLAB_URL'));

        $this->repoUrl = env('GITLAB_REPO_URL');
        $this->token = env('GITLAB_TOKEN');
        $this->projectId = env('GITLAB_PROJECT_ID');
    }

    public function createGitlabMR(PullRequest $pr, string $ticket, array $firstCommit): void
    {
        $this->gitlabClient->authenticate($this->token, GitlabClient::AUTH_HTTP_TOKEN);

        // create temp dir
        $tmpDir = sys_get_temp_dir() . '/' . uniqid('github-import-');

        // clone PR base repo
        exec('git clone ' . $pr->baseRepoCloneUrl . ' ' . $tmpDir);

        chdir($tmpDir);

        exec('git config user.email "bot@shopware.com"');
        exec('git config user.name "shopwareBot"');

        // add the github remote
        exec('git remote add github ' . $pr->baseRepoCloneUrl);

        // fetch the PR branch
        exec('git fetch github ' . $pr->branch);

        // checkout the PR branch
        $branch = 'next-' . $ticket . '/auto-imported-from-github';

        exec('git checkout -b ' . $branch . ' github/' . $pr->branch);

        // reset the branch to the first commit of the PR
        exec('git reset --soft ' . $firstCommit['sha']);

        // get first commit message
        $commitMessage = $firstCommit['commit']['message'];

        // check if the ticket number is already in the commit message, if not add it
        if (!str_starts_with($commitMessage, 'NEXT-' . $ticket)) {
            $commitMessage = 'NEXT-' . $ticket . ' - ' . $commitMessage;
        }

        // add the 'fixes #number' to the commit message
        $commitMessage .= "\nfixes #" . $pr->number;

        exec('git commit --amend -m "' . $commitMessage . '"');

        // push the branch to gitlab
        exec('git push -f -u ' . $this->repoUrl . ' ' . $branch);

        // create a merge request
        $this->gitlabClient->mergeRequests()->create(
            $this->projectId,
            $branch,
            $pr->targetBranch,
            $pr->title,
            [
                'description' => $pr->body . "\n\n---\n\nImported from Github. Please see: " . $pr->link,
                'labels' => 'github',
                'remove_source_branch' => true,
                'squash' => false,
                'allow_collaboration' => true,
            ]
        );

        // remove the temp dir
        exec('rm -rf ' . $tmpDir);
    }
}
