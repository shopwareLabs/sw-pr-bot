<?php

namespace GithubBot;

class Area
{
    private const EXTS = ['php', 'js', 'html.twig', 'yaml', 'xml'];
    private const SEARCHES = [
        "/#\[Package\('([a-zA-Z-]+)'\)\]/",
        "/@package\s+([a-zA-Z-]+)/",
    ];
    private const DIRECTORY_TO_AREA_MAPPINGS = [
        'src/Core/Checkout' => 'checkout',
        'src/Core' => 'core'
    ];

    public function __construct(private readonly FileDownloader $downloader)
    {
    }

    public function decide(array $files): AreaList
    {
        $areas = [];
        foreach ($files as $file) {
            if (!in_array(pathinfo($file['filename'], PATHINFO_EXTENSION), self::EXTS, true)) {
                continue;
            }

            $contents = $this->downloader->download($file['raw_url']);

            $areasForFile = [];
            foreach (self::SEARCHES as $search) {
                if (preg_match($search, $contents, $matches)) {
                    $areasForFile[] = $matches[1];
                }
            }

            if (empty($areasForFile)) {
                $pathParts = explode("/", $file['filename']);

                while(($part = array_pop($pathParts)) !== null) {
                    if (isset(self::DIRECTORY_TO_AREA_MAPPINGS[$part])) {
                        $areasForFile[] = self::DIRECTORY_TO_AREA_MAPPINGS[$part];
                        break;
                    }
                }
            }

            $areas = [...$areas, ...$areasForFile];
        }

        $counts = array_count_values($areas);
        $mostLikelyCandidate = array_search(max($counts), $counts, true);

        return new AreaList(array_values(array_unique($areas)), $mostLikelyCandidate);
    }
}