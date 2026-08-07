<?php

namespace nglasl\extensible;

use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\Queries\SQLDelete;
use SilverStripe\ORM\Queries\SQLUpdate;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Input\InputInterface;

/**
 * This creates an archived collection of analytics for each search page.
 * NOTE: The search analytics will be purged after this has taken place.
 * 
 * @author Nathan Glasl <nathan@symbiote.com.au>
 */
class ExtensibleSearchArchiveTask extends BuildTask
{
    protected static string $commandName = 'extensible-search-archive';

    private static string $segment = 'ExtensibleSearchArchiveTask';

    protected string $title = 'Extensible Search Archiving';

    protected static string $description = 'This creates an archived collection of analytics for each search page.';

    /**
     * The number of analytics to archive for each search page.
     */
    private static int $number_to_archive = 100;

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        set_time_limit(0);

        $limit = static::config()->get('number_to_archive');

        // Determine whether a search page has analytics.
        foreach (ExtensibleSearchPage::get() as $page) {
            $history = $page->History();
            if ($history->exists()) {

                // Instantiate an archive.
                $archive = ExtensibleSearchArchive::create([
                    'StartingDate' => $history->min('Created'),
                    'EndingDate' => $history->max('Created'),
                    'ExtensibleSearchPageID' => $page->ID,
                ]);
                $archive->write();

                // Determine the search page specific analytics.
                $counter = 0;
                foreach ($page->getHistorySummary() as $summary) {

                    // Determine whether the number of analytics to archive has been reached.
                    if ($counter++ === $limit) {
                        break;
                    }

                    // Instantiate an archived search analytic, and place it in the archive.
                    $archived = ExtensibleSearchArchived::create(
                        $summary->toMap()
                    );
                    $archived->ArchiveID = $archive->ID;
                    $archived->write();
                }

                // The search analytics will be purged now that they've been archived.
                $output->writeln("{$history->count()} Archived for page ID {$page->ID}");

                $deleteQuery = new SQLDelete(
                    'ExtensibleSearch',
                    ['ExtensibleSearchPageID = ?' => $page->ID]
                );
                $deleteQuery->execute();

                // The search suggestion frequencies depend on the analytics, so these require updating.
                $updateQuery = new SQLUpdate(
                    'ExtensibleSearchSuggestion',
                    ['Frequency' => 0],
                    ['ExtensibleSearchPageID = ?' => $page->ID]
                );
                $updateQuery->execute();
            }
        }

        $output->writeln('Complete!');

        return 0;
    }
}
