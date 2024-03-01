<?php

namespace GithubBotTest;

use GithubBot\Area;
use GithubBot\FakeDownloader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AreaTest extends TestCase
{
    public static function fileProvider(): array
    {
        return [
            'all-core' => [
                [
                    [
                        'path' => 'some/file.php',
                        'content' => <<<'EOT'
                            <?php
                            #[Package('core')]
                            echo "Hello Shopware";
                         EOT,
                    ]
                ],
                ['core'],
                'core'
            ],
            'mostly-core' => [
                [
                    [
                        'path' => 'some/file1.php',
                        'content' => <<<'EOT'
                            <?php
                            #[Package('core')]
                            echo "Hello Shopware";
                         EOT,
                    ],
                    [
                        'path' => 'some/file2.php',
                        'content' => <<<'EOT'
                            <?php
                            #[Package('core')]
                            echo "Hello Shopware";
                         EOT,
                    ],
                    [
                        'path' => 'some/file3.php',
                        'content' => <<<'EOT'
                            <?php
                            #[Package('buyers-experience')]
                            echo "Hello Shopware";
                         EOT,
                    ]
                ],
                ['core', 'buyers-experience'],
                'core'
            ]
        ];
    }

    #[DataProvider('fileProvider')]
    public function testAreasAreCorrectlyDecided(array $files, array $areas, string $mostLikelyCandidate): void
    {
        $downloader = new FakeDownloader(
            array_combine(
                array_map(static fn (array $file) => $file['path'], $files),
                array_map(static fn (array $file) => $file['content'], $files)
            )
        );
        $area = new Area($downloader);

        $list = $area->decide(
            array_map(static fn(array $file) => ['filename' => $file['path'], 'raw_url' => $file['path']], $files)
        );

        static::assertNotEmpty($list->all);
        static::assertSame($areas, $list->all);
        static::assertSame($mostLikelyCandidate, $list->mostLikelyCandidate);
    }
}
