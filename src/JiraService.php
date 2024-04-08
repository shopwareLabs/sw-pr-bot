<?php

namespace GithubBot;

use DH\Adf\Node\Block\Document;
use JiraCloud\ADF\AtlassianDocumentFormat;
use JiraCloud\Configuration\ArrayConfiguration;
use JiraCloud\Issue\IssueField;
use JiraCloud\Issue\IssueService;

class JiraService
{
    private string $host;
    private string $project;
    private string $issueType;
    private string $token;

    private const LABEL_TO_AREA_MAPPINGS = [
        'Area: Core' => 'Platform | Core',
        'Area: Buyers Experience' => 'Features | Buyers Experience',
        'Area: Administration' => 'Platform | Admin',
        'Area: Storefront' => 'Platform | Storefront',
        'Area: Checkout & Fulfilment' => 'Features | Checkout & Fulfilment',
        'Area: Inventory Managment' => 'Features | Inventory Management',
        'Area: Services & Settings' => 'Features | Services & Settings',
    ];

    private const AREA_TO_TEAM_MAPPING = [
        'Area: Core' => 'CT Core',
        'Area: Buyers Experience' => 'ST Byte Club',
        'Area: Administration' => 'CT Admin',
        'Area: Storefront' => 'CT Storefront',
        'Area: Checkout & Fulfilment' => 'ST Codebusters',
        'Area: Inventory Managment' => 'ST Barware',
        'Area: Services & Settings' => 'ST Runtime Terror',
    ];

    public function __construct()
    {
        $this->host = env('JIRA_HOST');
        $this->project = env('JIRA_PROJECT');
        $this->issueType = env('JIRA_ISSUE_TYPE');
        $this->token = env('JIRA_TOKEN');
    }

    public function createTicket(PullRequest $pr, string $area): string
    {
        $issueField = new IssueField();

        $document = (new Document())
            ->paragraph()->text($pr->body . "\n\n---\n\nImported from Github. Please see: " . $pr->link)->end();

        $description = new AtlassianDocumentFormat($document);

        $issueField
            ->setProjectKey($this->project)
            ->setIssueTypeAsString($this->issueType)
            ->setSummary('[Github]' . $pr->title)
            ->setDescription($description)
            ->addLabelAsString('Github')
            // Product Area
            ->addCustomField('customfield_14101', ['value' => self::LABEL_TO_AREA_MAPPINGS[$area] ?? self::LABEL_TO_AREA_MAPPINGS['Area: Core']])
            // Team
            ->addCustomField('customfield_12000', ['value' => self::AREA_TO_TEAM_MAPPING[$area] ?? self::AREA_TO_TEAM_MAPPING['Area: Core']])
            // PR Link
            ->addCustomField('customfield_12100', $pr->link)
            // Is Public?
            ->addCustomField('customfield_10202', ['id' => '10110'])
            // Author
            ->addCustomField('customfield_12101', $pr->author);

        $issueService = new IssueService(new ArrayConfiguration([
            'jiraHost' => $this->host,
            'jiraUser' => 'j.damokos@shopware.com',
            'personalAccessToken' => $this->token,
        ]));

        $issue = $issueService->create($issueField);

        return substr($issue->key, 5);
    }

    public function parseTicketNumber(string $body): ?string
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
}
