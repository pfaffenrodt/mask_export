<?php
namespace IchHabRecht\MaskExport\FileCollection;

/*
 * This file is part of the TYPO3 extension mask_export.
 *
 * (c) 2016 Nicole Cordes <typo3@cordes.co>, CPS-IT GmbH
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use IchHabRecht\MaskExport\Aggregate\PhpAwareInterface;

class PhpFileCollection extends AbstractFileCollection
{
    /**
     * @return array
     */
    protected function processAggregateCollection()
    {
        $files = [];
        foreach ($this->aggregateCollection as $aggregate) {
            if (!$aggregate instanceof PhpAwareInterface) {
                continue;
            }

            $aggregateFiles = $aggregate->getPhpFiles();
            foreach ($aggregateFiles as $file => $content) {
                if (!isset($files[$file])) {
                    $files[$file] = '<?php' . PHP_EOL;
                }
                $files[$file] .= $content;
            }
        }

        return $files;
    }
}
