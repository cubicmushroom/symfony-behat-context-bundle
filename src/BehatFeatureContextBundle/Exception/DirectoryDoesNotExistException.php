<?php
/**
 * Created by PhpStorm.
 * User: toby
 * Date: 05/03/2016
 * Time: 15:28
 */

namespace CubicMushroom\Symfony\BehatFeatureContextBundle\Exception;

use Behat\Behat\Context\Exception\ContextException;

/**
 * Thrown when expected directory is missing
 *
 * @package CubicMushroom\Symfony\BehatFeatureContextBundle
 */
class DirectoryDoesNotExistException extends AbstractException implements ContextException
{
    /**
     * Creates exception with default message
     *
     * @param string $dir
     *
     * @return static
     */
    public static function create($dir)
    {
        return new static("Expected directory '{$dir}' does not exist'");
    }
}