<?php
/**
 * Created by PhpStorm.
 * User: toby
 * Date: 05/03/2016
 * Time: 14:52
 */

namespace CubicMushroom\Symfony\BehatFeatureContextBundle\Exception;

use Behat\Behat\Context\Exception\ContextException;

/**
 * Thrown when expected number of files is not found
 *
 * @package CubicMushroom\Symfony\BehatFeatureContextBundle
 */
class IncorrectFileCountException extends AbstractException implements ContextException
{
    /**
     * Creates exception with default message
     *
     * @param int $expectedFileCount
     * @param int $actualFileCount
     *
     * @return static
     */
    public static function create($expectedFileCount, $actualFileCount)
    {
        return new static(sprintf('Expected %d files, but found %d', $expectedFileCount, $actualFileCount));
    }
}