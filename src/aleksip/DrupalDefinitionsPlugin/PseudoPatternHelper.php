<?php

namespace aleksip\DrupalDefinitionsPlugin;

use PatternLab\PatternData;

/**
 * Merges base pattern data with pseudo-pattern data.
 *
 * @author Aleksi Peebles <aleksi@iki.fi>
 */
class PseudoPatternHelper
{
    public function run()
    {
        $store = PatternData::get();
        foreach ($store as $patternStoreKey => $patternStoreData) {
            if (isset($patternStoreData['pseudo']) && $patternStoreData['pseudo'] === true) {
                $this->processPattern($patternStoreKey, $patternStoreData['original']);
            }
        }
    }

    protected function processPattern($pseudoPatternKey, $basePatternKey)
    {
        // We only do anything if the base pattern has data.
        if ($basePatternData = PatternData::getPatternOption($basePatternKey, 'data')) {
            if (!$pseudoPatternData = PatternData::getPatternOption($pseudoPatternKey, 'data')) {
                $pseudoPatternData['data'] = array();
            }

            // Merge base pattern data with pseudo-pattern data.
            $patternStoreData = [
                'data' => array_replace_recursive($basePatternData, $pseudoPatternData),
            ];

            // Merge with existing data in the store, replacing existing values.
            $patternStoreData = PatternData::checkOption($pseudoPatternKey)
                ? array_replace_recursive(PatternData::getOption($pseudoPatternKey), $patternStoreData)
                : $patternStoreData;

            PatternData::setOption($pseudoPatternKey, $patternStoreData);
        }
    }
}
