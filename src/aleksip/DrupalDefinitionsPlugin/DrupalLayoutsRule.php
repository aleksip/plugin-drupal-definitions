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

        $layoutData = array();

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

            $baseTemplateParts = explode('/', $definition['template']);
            $baseTemplateName = end($baseTemplateParts).'.html';

            // The template must exist in the same directory as the definition file.
            if (!file_exists($fullPath.'/'.$baseTemplateName.'.twig')) {
                continue;
            }

            // The definition must have an 'example_values' section.
            if (empty($definition['example_values'])) {
                continue;
            }

            // Process the 'example_values' section.
            foreach ($definition['example_values'] as $pattern => $exampleValues) {
                foreach ($exampleValues as $key => $data) {
                    switch ($key) {
                        // Pattern documentation metadata is under the 'meta' key.
                        case 'meta':
                            $patternFileName = $this->getPatternFileName($pattern, $baseTemplateName, 'md', '-');
                            $patternPathName = $this->getPatternPathName($pattern, $pathName, $name, $baseTemplateName, $patternFileName);
                            $this->processPatternMeta($depth, 'md', $path, $patternPathName, $patternFileName, $data);
                            break;

                        // Pattern data is under the 'data' key.
                        case 'data':
                            if ('base' === $pattern) {
                                // The 'base' key is reserved for the base pattern.
                                $patternFileName = $this->getPatternFileName($pattern, $baseTemplateName, 'twig');
                                $patternPathName = $this->getPatternPathName($pattern, $pathName, $name, $baseTemplateName, $patternFileName);
                                $this->processBasePatternData($depth, 'twig', $path, $patternPathName, $patternFileName, $data);
                            } else {
                                // All other keys are treated as pseudo-patterns.
                                $patternFileName = $this->getPatternFileName($pattern, $baseTemplateName, 'yml');
                                $patternPathName = $this->getPatternPathName($pattern, $pathName, $name, $baseTemplateName, $patternFileName);
                                $this->processPseudoPatternData($depth, 'yml', $path, $patternPathName, $patternFileName, $data);
                            }
                            break;
                    }
                }
            }
        }

        // Remove the layout definition file from the store. (It is placed there
        // by core's PatternInfoRule).
        //
        // The extra store entry does not seem to cause any problems so leaving
        // the call out for now.
        //$this->unsetPatternStoreOption($this->getPatternStoreKey($ext, $name));
    }

    protected function getPatternFileName($pattern, $patternFileName, $ext, $separator = '~')
    {
        if ('base' === $pattern) {
            $patternFileName = $patternFileName.'.'.$ext;
        } else {
            if (in_array($pattern[0], PatternData::getFrontMeta())) {
                $patternFileName = $pattern[0].$patternFileName.$separator.substr($pattern, 1).'.'.$ext;
            } else {
                $patternFileName = $patternFileName.$separator.$pattern.'.'.$ext;
            }
        }

        return $patternFileName;
    }

    protected function getPatternPathName($pattern, $pathName, $name, $baseTemplateName, $patternName)
    {
        if ('base' === $pattern) {
            $patternPathName = str_replace($name, $baseTemplateName, $pathName);
        } else {
            $patternPathName = str_replace($name, $patternName, $pathName);
        }

        return $patternPathName;
    }

    protected function getPatternStoreKey($ext, $name)
    {
        $patternTypeDash = PatternData::getPatternTypeDash();
        $pattern         = str_replace(".".$ext, "", $name);        // foo
        $patternDash     = $this->getPatternName($pattern, false); // foo
        $patternPartial  = $patternTypeDash."-".$patternDash;     // atoms-foo

        return $patternPartial;
    }

    protected function unsetPatternStoreOption($optionName)
    {
        $store = PatternData::get();
        if (isset($store[$optionName])) {
            unset($store[$optionName]);
            PatternData::clear();
            foreach ($store as $optionName => $optionValue) {
                PatternData::setOption($optionName, $optionValue);
            }
        }
    }

    /**
     * @see \PatternLab\PatternData\Rules\DocumentationRule::run()
     */
    protected function processPatternMeta($depth, $ext, $path, $pathName, $name, $yaml)
    {
        // load default vars
        $patternType        = PatternData::getPatternType();
        $patternTypeDash    = PatternData::getPatternTypeDash();
        $dirSep             = PatternData::getDirSep();

        // set-up the names, $name == 00-colors.md
        $doc        = str_replace(".".$ext, "", $name);              // 00-colors
        $docDash    = $this->getPatternName(str_replace("_", "", $doc), false); // colors
        $docPartial = $patternTypeDash."-".$docDash;

        // default vars
        $patternSourceDir = Config::getOption("patternSourceDir");

        // grab the title and unset it from the yaml so it doesn't get duped in the meta
        if (isset($yaml["title"])) {
            $title = $yaml["title"];
            unset($yaml["title"]);
        }

        // figure out if this is a pattern subtype
        $patternSubtypeDoc = false;

        $category         = ($patternSubtypeDoc) ? "patternSubtype" : "pattern";
        $patternStoreKey  = ($patternSubtypeDoc) ? $docPartial."-plsubtype" : $docPartial;

        $patternStoreData = array(
            "category"   => $category,
            "meta"       => $yaml,
            "full"       => $doc,
        );

        // can set `title: My Cool Pattern` instead of lifting from file name
        if (isset($title)) {
            $patternStoreData["nameClean"] = $title;
        }

        $availableKeys = [
          'state', // can use `state: inprogress` instead of `button@inprogress.mustache`
          'hidden', // setting to `true`, removes from menu and viewall, which is same as adding `_` prefix
          'noviewall', // setting to `true`, removes from view alls but keeps in menu, which is same as adding `-` prefix
          'order', // @todo implement order
          'tags', // not implemented, awaiting spec approval and integration with styleguide kit. adding to be in sync with Node version.
          'links', // not implemented, awaiting spec approval and integration with styleguide kit. adding to be in sync with Node version.
        ];

        foreach ($availableKeys as $key) {
            if (isset($yaml[$key])) {
                $patternStoreData[$key] = $yaml[$key];
            }
        }

        // if the pattern data store already exists make sure this data overwrites it
        $patternStoreData = (PatternData::checkOption($patternStoreKey)) ? array_replace_recursive(PatternData::getOption($patternStoreKey), $patternStoreData) : $patternStoreData;
        PatternData::setOption($patternStoreKey, $patternStoreData);
    }

    /**
    * @see \PatternLab\PatternData\Rules\PatternInfoRule::run()
    */
    protected function processBasePatternData($depth, $ext, $path, $pathName, $name, $data)
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
    protected function processPseudoPatternData($depth, $ext, $path, $pathName, $name, $data)
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
