<?php

namespace Toast\ColourPalettes\Tasks;

use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Toast\ColourPalettes\Helpers\Helper;
use Toast\ColourPalettes\Models\Colour;

/**
 * Migrates colour data from the m4 module (Colour.Colour / Colour.CustomColourID and the ColourPalette
 * table) to the m6 structure (Colour.HexValue / Colour.CSSName and has_one Colour relations).
 *
 * Safe to run more than once. Run with `--dry-run` to see what would change:
 *
 *     sake tasks:UpdateColoursTask --dry-run
 *     sake tasks:UpdateColoursTask
 *
 * Extensions can use `onBeforeMigrateColours`, `onAfterMigrateColours` and `updateMigratedParent`.
 */
class UpdateColoursTask extends BuildTask
{
    protected static string $commandName = 'UpdateColoursTask';

    protected string $title = 'Update Colours from m4 to m6 Task';

    protected static string $description = 'Updates the Colours table to be compatible with m6';

    public function getOptions(): array
    {
        return [
            new InputOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would change without writing anything'),
        ];
    }

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $dryRun = (bool) $input->getOption('dry-run');

        $this->extend('onBeforeMigrateColours', $dryRun);

        if ($dryRun) {
            $output->writeln('<comment>Dry run: nothing will be written</comment>');
        }

        $result = Helper::withoutCSSGeneration(function () use ($output, $dryRun): int {
            $colours = $this->migrateColours($output, $dryRun);
            $relations = $this->migrateColourPalettes($output, $dryRun);

            $output->writeln("Updated {$colours} colours and {$relations} colour relations.");

            return Command::SUCCESS;
        });

        $this->extend('onAfterMigrateColours', $dryRun);

        return $result;
    }

    /**
     * Copy the legacy Colour / CustomColourID fields into HexValue / CSSName.
     *
     * @return int The number of colours updated
     */
    protected function migrateColours(PolyOutput $output, bool $dryRun): int
    {
        $updated = 0;

        /** @var Colour $colour */
        foreach (Colour::get() as $colour) {
            // Skip if already migrated
            if ($colour->CSSName && $colour->HexValue) {
                continue;
            }

            if (!$colour->Colour) {
                $output->writeln("Skipping Colour ID {$colour->ID} as it has no legacy Colour value");
                continue;
            }

            if (!$colour->CSSName && $colour->CustomColourID) {
                $colour->CSSName = $colour->CustomColourID;
            }

            if (!$colour->HexValue) {
                $colour->HexValue = $colour->Colour;
            }

            if (!$dryRun) {
                $colour->write();
            }

            $updated++;
            $output->writeln("Updated Colour ID {$colour->ID} with CSSName {$colour->CSSName} and HexValue {$colour->HexValue}");
        }

        return $updated;
    }

    /**
     * Point each parent's `{Field}ID` relation at the Colour recorded in the legacy ColourPalette table.
     *
     * @return int The number of relations updated
     */
    protected function migrateColourPalettes(PolyOutput $output, bool $dryRun): int
    {
        if (!DB::get_schema()->hasTable('ColourPalette')) {
            $output->writeln('No ColourPalette table found, nothing to migrate');
            return 0;
        }

        $updated = 0;
        $palettes = DB::query('SELECT * FROM "ColourPalette" WHERE "ColourID" != 0');

        foreach ($palettes as $palette) {
            // The palette title is the field name, ParentClass / ParentID point at the object that owns it
            $fieldName = $palette['Title'];
            $objectClass = $palette['ParentClass'];
            $objectID = (int) $palette['ParentID'];
            $relationField = $fieldName . 'ID';

            if (!class_exists($objectClass) || !is_a($objectClass, DataObject::class, true)) {
                $output->writeln("Skipping ColourPalette ID {$palette['ID']} as class {$objectClass} does not exist");
                continue;
            }

            /** @var DataObject|null $parentObject */
            $parentObject = $objectClass::get()->byID($objectID);

            if (!$parentObject) {
                $output->writeln("Could not find {$objectClass} ID {$objectID} for ColourPalette ID {$palette['ID']}");
                continue;
            }

            if (!$parentObject->hasField($relationField)) {
                $output->writeln("{$objectClass} ID {$objectID} does not have field {$relationField} for ColourPalette ID {$palette['ID']}");
                continue;
            }

            $this->extend('updateMigratedParent', $parentObject, $palette);

            if ($dryRun) {
                $updated++;
                $output->writeln("Would set {$objectClass} ID {$objectID} {$relationField} to Colour ID {$palette['ColourID']}");
                continue;
            }

            // Only republish records that were published before the change
            $wasPublished = $parentObject->hasMethod('isPublished') && $parentObject->isPublished();

            $parentObject->$relationField = $palette['ColourID'];
            $parentObject->write();

            if ($wasPublished && $parentObject->hasMethod('publishRecursive')) {
                $parentObject->publishRecursive();
                $output->writeln("Published {$objectClass} ID {$objectID}");
            }

            $updated++;
            $output->writeln("Migrated ColourPalette ID {$palette['ID']} to Colour ID {$palette['ColourID']}");
        }

        return $updated;
    }
}
