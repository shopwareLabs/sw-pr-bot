<?php

namespace GithubBot;

class Area
{
    private const EXTENSIONS = ['php', 'js', 'html.twig', 'yaml', 'xml'];

    private const SEARCHES = [
        "/#\[Package\('([a-zA-Z-]+)'\)\]/",
        "/@package\s+([a-zA-Z-]+)/",
    ];

    private const PACKAGE_TO_AREA_MAPPINGS = [
        'core' => 'Area: Core',
        'buyers-experience' => 'Area: Buyers Experience',
        'administration' => 'Area: Administration',
        'storefront' => 'Area: Storefront',
        'checkout' => 'Area: Checkout & Fulfilment',
        'inventory' => 'Area: Inventory Managment',
        'services-settings' => 'Area: Services & Settings',
    ];

    private const DIRECTORY_TO_AREA_MAPPINGS = [
        'src/Core' => 'Area: Core',
        'src/Core/Administration' => 'Area: Administration',
        'src/Core/Checkout' => 'Area: Checkout & Fulfilment',
        'src/Storefront' => 'Area: Storefront',
    ];

    private const DEFAULT_AREA = 'Area: Core';

    public function __construct(private readonly FileDownloader $downloader)
    {
    }

    public function decide(array $files): AreaList
    {
        $areas = [];
        foreach ($files as $file) {
            if (!in_array(pathinfo($file['filename'], PATHINFO_EXTENSION), self::EXTENSIONS, true)) {
                continue;
            }

            $contents = $this->downloader->download($file['raw_url']);

            $areasForFile = [];

            foreach (self::SEARCHES as $search) {
                if (preg_match($search, $contents, $matches)) {
                    $package = $matches[1];

                    if (isset(self::PACKAGE_TO_AREA_MAPPINGS[$package])) {
                        $areasForFile[] = self::PACKAGE_TO_AREA_MAPPINGS[$package];
                    }
                }
            }

            if (empty($areasForFile)) {
                $pathParts = explode('/', $file['filename']);

                while(($part = array_pop($pathParts)) !== null) {
                    if (isset(self::DIRECTORY_TO_AREA_MAPPINGS[$part])) {
                        $areasForFile[] = self::DIRECTORY_TO_AREA_MAPPINGS[$part];
                        break;
                    }
                }
            }

            $areas = [...$areas, ...$areasForFile];
        }

        if (empty($areas)) {
            $areas[] = self::DEFAULT_AREA;
        }

        $counts = array_count_values($areas);
        $mostLikelyCandidate = array_search(max($counts), $counts, true);

        return new AreaList(array_values(array_unique($areas)), $mostLikelyCandidate);
    }
}