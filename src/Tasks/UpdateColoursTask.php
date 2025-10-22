<?php

namespace Toast\Tasks;

use Toast\Blocks\Block;
use SilverStripe\ORM\DB;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Image;
use SilverStripe\Dev\BuildTask;
use Toast\Blocks\MediaTextBlock;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Config\Config;
use SilverStripe\Control\HTTPRequest;
use Toast\ColourPalettes\Models\Colour;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use SilverStripe\FullTextSearch\Search\Updaters\SearchUpdater;

class UpdateColoursTask extends BuildTask
{
    protected static string $commandName = 'UpdateColoursTask';

    protected string $title = 'Update Colours from m4 to m6 Task';

    protected static string $description = 'Updates the Colours table to be compatible with m6';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $request = Injector::inst()->get(HTTPRequest::class);
        if (!$request->getVar('confirm')) {
            echo 'Please add ?confirm=1 to the URL to proceed.' . PHP_EOL;
            return Command::FAILURE;
        }

        $colours = DB::query('SELECT * FROM "Colour"');
        foreach ($colours as $colour) {
            // cssname is the new customcolourid
            $existingColour = Colour::get()->byID($colour['ID']);
            if (!$existingColour) {
                echo "Could not find Colour ID {$colour['ID']} <br>" . PHP_EOL;
                continue;
            }
            // skip if $colour['Colour'] is empty
            if (!$colour['Colour']) {
                echo "Skipping Colour ID {$colour['ID']} as it has no Colour value <br>" . PHP_EOL;
                continue;
            }
            // skip if cssname already exists
            if($existingColour->CSSName && $existingColour->HexValue) {
                continue;
            }
            $existingColour->CSSName = $colour['CustomColourID'];
            $existingColour->HexValue = $colour['Colour'];
            $existingColour->write();
            echo "Updated Colour ID {$colour['ID']} with CSSName {$colour['CustomColourID']} and HexValue {$colour['Colour']} <br>" . PHP_EOL;
        }

        // query the ColourPalette table for all records
        $colourPalettes = DB::query('SELECT * FROM "ColourPalette" WHERE "ColourID" != 0');

        foreach($colourPalettes as $palette) {
            if(!$palette['ColourID']) {
                echo "Skipping ColourPalette ID {$palette['ID']} as it has no ColourID <br>" . PHP_EOL;
                continue;
            }
            // palette title is the fieldname
            // ParentClass the object class - could be instanceof SiteConfig or Page or DataObject
            // ParentID the ID of the object
            $fieldName = $palette['Title'];
            $objectClass = $palette['ParentClass'];
            $objectID = $palette['ParentID'];

            // check the object class exists
            if(!class_exists($objectClass)) {
                echo "Skipping ColourPalette ID {$palette['ID']} as class {$objectClass} does not exist <br>" . PHP_EOL;
                continue;
            }
            // we want to find the parent object and define the colour to be the fieldname of the object
            $parentObject = $objectClass::get()->byID($objectID);
            echo "Processing ColourPalette ID {$palette['ID']} for {$objectClass} ID {$objectID} <br>" . PHP_EOL;
            // echo $parentObject instanceof Block ? 'true<br>' : 'false<br>';
            // if instance of block check with block
            if ($parentObject instanceof Block) {
                $parentObject = Block::get()->byID($objectID);
                echo "Found Block parent object <br>" . PHP_EOL;
            }
            if (!$parentObject) {
                echo "Could not find parent object for ColourPalette ID {$palette['ID']} <br>" . PHP_EOL;
                continue;
            }
            if (!$parentObject->{$fieldName . 'ID'}) {
                echo "Parent object {$parentObject} does not have field {$fieldName}ID for ColourPalette ID {$palette['ID']} <br>" . PHP_EOL;
                continue;
            }
            // update the field to be the ColourID
            $parentObject->{$fieldName . 'ID'} = $palette['ColourID'];
            $parentObject->write();
            if ($parentObject instanceof Block || $parentObject instanceof SiteTree) {
                // if block or site tree, publish the object
                $parentObject->publishRecursive();
                echo "Published parent object <br>" . PHP_EOL;
            }

            echo "Migrated ColourPalette ID {$objectID} to Colour ID {$palette['ColourID']} <br>" . PHP_EOL;
        }

        //echo the total number of colourpalettes processed
        echo "Processed " . $colourPalettes->numRecords() . " ColourPalettes." . PHP_EOL;
        
        return Command::SUCCESS;
    }

}