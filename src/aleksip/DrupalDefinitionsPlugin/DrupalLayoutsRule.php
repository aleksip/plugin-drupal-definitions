<?php

namespace aleksip\DrupalDefinitionsPlugin;

use PatternLab\Config;
use PatternLab\Console;
use PatternLab\PatternData;
use PatternLab\PatternData\Rule;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Pattern data Rule to process Drupal layout definitions.
 *
 * @author Aleksi Peebles <aleksi@iki.fi>
 */
class DrupalLayoutsRule extends Rule
{
    public function __construct($options = array())
    {
        parent::__construct($options);

        $this->depthProp  = 3;
        $this->extProp    = 'yml';
        $this->isDirProp  = false;
        $this->isFileProp = true;
        $this->searchProp = '.layouts.';
        $this->ignoreProp = '';
    }

    public function run($depth, $ext, $path, $pathName, $name)
    {
        $fullPath = Config::getOption('patternSourceDir').'/'.$path;

        $file = file_get_contents($fullPath.'/'.$name);

        try {
            $layoutData = Yaml::parse($file);
        } catch (ParseException $e) {
            Console::writeWarning('unable to parse '.$name);
        }

        if (gettype($layoutData) === 'string') {
            $layoutData = array();
        }

        // Process each layout definition in the .layouts.yml file.
        foreach ($layoutData as $definition) {
            // A template must be defined.
            if (empty($definition['template'])) {
                continue;
            }

            // The template must exist in the same directory as the definition file.
            $parts = explode('/', $definition['template']);
            $name = end($parts).'.html.twig';
            $ext = 'twig';
            if (!file_exists($fullPath.'/'.$name)) {
                continue;
            }

            // The definition must have an 'example_values' section.
            if (!empty($definition['example_values'])) {
                foreach ($definition['example_values'] as $pattern => $data) {
                    if ('base' === $pattern) {
                        // The 'base' key is reserved for the base pattern.
                        $this->processBasePattern($depth, $ext, $path, $pathName, $name, $data);
                    } else {
                        // All other keys are treated as pseudo-patterns.
                        if (in_array($pattern[0], PatternData::getFrontMeta())) {
                            $pseudoPatternName = $pattern[0].substr($name, 0, -5).'~'.substr($pattern, 1).'.twig';
                        } else {
                            $pseudoPatternName = substr($name, 0, -5).'~'.$pattern.'.twig';
                        }
                        $this->processPseudoPattern($depth, $ext, $path, $pathName, $pseudoPatternName, $data);
                    }
                }
            }
        }
    }

    /**
    * @see \PatternLab\PatternData\Rules\PatternInfoRule::run()
    */
    protected function processBasePattern($depth, $ext, $path, $pathName, $name, $data)
    {
        // load default vars
        $patternTypeDash = PatternData::getPatternTypeDash();

        // set-up the names, $name == foo.json
        $pattern         = str_replace(".".$ext, "", $name);        // foo
        $patternDash     = $this->getPatternName($pattern, false); // foo
        $patternPartial  = $patternTypeDash."-".$patternDash;     // atoms-foo

        $patternStoreData = array("category" => "pattern");

        $patternStoreData["data"] = $data;

        // create a key for the data store
        $patternStoreKey = $patternPartial;

        // if the pattern data store already exists make sure it is merged and overwrites this data
        $patternStoreData = (PatternData::checkOption($patternStoreKey)) ? array_replace_recursive(PatternData::getOption($patternStoreKey), $patternStoreData) : $patternStoreData;
        PatternData::setOption($patternStoreKey, $patternStoreData);
    }

    /**
    * @see \PatternLab\PatternData\Rules\PseudoPatternRule::run()
    */
    protected function processPseudoPattern($depth, $ext, $path, $pathName, $name, $data)
    {
        // load default vars
        $patternSubtype      = PatternData::getPatternSubtype();
        $patternSubtypeClean = PatternData::getPatternSubtypeClean();
        $patternSubtypeDash  = PatternData::getPatternSubtypeDash();
        $patternType         = PatternData::getPatternType();
        $patternTypeClean    = PatternData::getPatternTypeClean();
        $patternTypeDash     = PatternData::getPatternTypeDash();
        $dirSep              = PatternData::getDirSep();
        $frontMeta           = PatternData::getFrontMeta();

        // should this pattern get rendered?
        $hidden             = ($name[0] === "_");
        $noviewall          = ($name[0] === "-");

        // set-up the names
        $patternFull        = in_array($name[0], $frontMeta) ? substr($name, 1) : $name;         // 00-colors~foo.mustache
        $patternState       = "";

        // check for pattern state
        if (strpos($patternFull, "@") !== false) {
            $patternBits    = explode("@", $patternFull, 2);
            $patternState   = str_replace(".".$ext, "", $patternBits[1]);
            $patternFull    = preg_replace("/@(.*?)\./", ".", $patternFull);
        }

        // finish setting up vars
        $patternBits         = explode("~", $patternFull);
        $patternBase         = $patternBits[0].".".Config::getOption("patternExtension");       // 00-homepage.mustache
        $patternBaseDash     = $this->getPatternName($patternBits[0], false);                    // homepage
        $patternBaseOrig     = $patternTypeDash."-".$patternBaseDash;                           // pages-homepage
        $patternBaseData     = $patternBits[0].".".$ext;                                        // 00-homepage.json
        $stripJSON           = str_replace(".".$ext, "", $patternBits[1]);
        $patternBitClean     = preg_replace("/@(.*?)/", "", $patternBits[0]);
        $pattern             = $patternBitClean."-".$stripJSON;                                 // 00-homepage-00-emergency
        $patternInt          = $patternBitClean."-".$this->getPatternName($stripJSON, false);   // 00-homepage-emergency
        $patternDash         = $this->getPatternName($patternInt, false);                        // homepage-emergency
        $patternClean        = str_replace("-", " ", $patternDash);                               // homepage emergency
        $patternPartial      = $patternTypeDash."-".$patternDash;                               // pages-homepage-emergency
        $patternPath         = str_replace(".".$ext, "", str_replace("~", "-", $pathName));         // 00-atoms/01-global/00-colors
        $patternPathDash     = str_replace($dirSep, "-", $patternPath);                           // 00-atoms-01-global-00-colors (file path)

        // check the original pattern path. if it doesn't exist make a guess
        $patternPathOrig     = PatternData::getPatternOption($patternBaseOrig, "pathName");      // 04-pages/00-homepage
        $patternPathOrigDash = PatternData::getPatternOption($patternBaseOrig, "pathDash");      // 04-pages-00-homepage
        if (!$patternPathOrig) {
            $patternPathOrigBits = explode("~", $pathName);
            $patternPathOrig     = $patternPathOrigBits[0];                                     // 04-pages/00-homepage
            $patternPathOrigDash = str_replace($dirSep, "-", $patternPathOrig);                   // 04-pages-00-homepage
        }

        // create a key for the data store
        $patternStoreKey     = $patternPartial;

        // collect the data
        $patternStoreData = array(
            "category"     => "pattern",
            "name"         => $pattern,
            "partial"      => $patternPartial,
            "nameDash"     => $patternDash,
            "nameClean"    => $patternClean,
            "type"         => $patternType,
            "typeDash"     => $patternTypeDash,
            "breadcrumb"   => array("patternType" => $patternTypeClean),
            "state"        => $patternState,
            "hidden"       => $hidden,
            "noviewall"    => $noviewall,
            "depth"        => $depth,
            "ext"          => $ext,
            "path"         => $path,
            "pathName"     => $patternPath,
            "pathDash"     => $patternPathDash,
            "isDir"        => $this->isDirProp,
            "isFile"       => $this->isFileProp,
            "pseudo"       => true,
            "original"     => $patternBaseOrig,
            "pathOrig"     => $patternPathOrig,
            "pathOrigDash" => $patternPathOrigDash,
        );

        // add any subtype info if necessary
        if ($depth > 1) {
            $patternStoreData["subtype"]     = $patternSubtype;
            $patternStoreData["subtypeDash"] = $patternSubtypeDash;
            $patternStoreData["breadcrumb"]  = array("patternType" => $patternTypeClean, "patternSubtype" => $patternSubtypeClean);
        }

        $patternStoreData["data"] = $data;

        // if the pattern data store already exists make sure it is merged and overwrites this data
        if (PatternData::checkOption($patternStoreKey)) {
            $existingData = PatternData::getOption($patternStoreKey);
            if (array_key_exists('nameClean', $existingData)) {
                // don't overwrite nameClean
                unset($patternStoreData['nameClean']);
            }
            $patternStoreData = array_replace_recursive($existingData, $patternStoreData);
        }
        PatternData::setOption($patternStoreKey, $patternStoreData);
    }
}
