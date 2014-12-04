<?php

/*
 * This file is part of the Distill package.
 *
 * (c) Raul Fraile <raulfraile@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Distill\Strategy;

use Distill\File;

/**
 * Minimum size strategy.
 *
 * The goal of this strategy is to try to use compressed files with better
 * compression ratio.
 *
 * @author Raul Fraile <raulfraile@gmail.com>
 */
class MinimumSize extends AbstractStrategy
{

    /**
     * {@inheritdoc}
     */
    public static function getName()
    {
        return 'minimum_size';
    }

    /**
     * Order files based on the strategy.
     * @param File $file1 File 1
     * @param File $file2 File 2
     *
     * @return int
     */
    protected function order(File $file1, File $file2)
    {
        $priority1 = $file1->getFormat()->getCompressionRatioLevel();
        $priority2 = $file2->getFormat()->getCompressionRatioLevel();

        if ($priority1 == $priority2) {
            return 0;
        }

        return ($priority1 > $priority2) ? -1 : 1;
    }
}