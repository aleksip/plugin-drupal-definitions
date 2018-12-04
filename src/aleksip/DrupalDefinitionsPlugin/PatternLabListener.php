<?php

namespace aleksip\DrupalDefinitionsPlugin;

use PatternLab\Config;
use PatternLab\Console;
use PatternLab\Listener;
use PatternLab\PatternData;

/**
 * @author Aleksi Peebles <aleksi@iki.fi>
 */
class PatternLabListener extends Listener
{
    public function __construct()
    {
        $this->addListener('patternData.rulesLoaded', 'addDrupalDefinitionRules');
        $this->addListener('patternData.dataLoaded', 'processPseudoPatternData');
    }

    public function addDrupalDefinitionRules()
    {
        if (!$this->isEnabled()) {
            return;
        }

        PatternData::setRule('DrupalLayoutsRule', new DrupalLayoutsRule());
    }

    public function processPseudoPatternData()
    {
        if (!$this->isEnabled()) {
            return;
        }

        $pseudoPatternHelper = new PseudoPatternHelper();
        $pseudoPatternHelper->run();
    }

    protected function isEnabled()
    {
        $enabled = Config::getOption('plugins.drupalDefinitions.enabled');
        $enabled = (is_null($enabled) || (bool) $enabled);

        if ($this->isVerbose() && !$enabled) {
            Console::writeLine('drupal definitions plugin is disabled...');
        }

        return $enabled;
    }

    protected function isVerbose()
    {
        $verbose = Config::getOption('plugins.drupalDefinitions.verbose');

        return (!is_null($verbose) && (bool) $verbose);
    }
}
