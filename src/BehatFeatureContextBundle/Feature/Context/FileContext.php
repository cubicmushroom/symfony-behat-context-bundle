<?php
/**
 * Created by PhpStorm.
 * User: toby
 * Date: 05/03/2016
 * Time: 14:40
 */

namespace CubicMushroom\Symfony\BehatFeatureContextBundle\Feature\Context;

use Behat\Behat\Context\Context;
use CubicMushroom\Symfony\BehatFeatureContextBundle\Exception\DirectoryDoesNotExistException;

/**
 * Class FileContext
 *
 * @package CubicMushroom\Symfony\BehatFeatureContextBundle
 */
class FileContext implements Context
{
    /**
     * Base path for files
     *
     * @var string
     */
    protected $basePath;


    /**
     * FileContext constructor.
     *
     * @param string $basePath
     */
    public function __construct($basePath)
    {
        $this->basePath = $basePath;
    }


    /**
     * @Given /^directory "(?P<dir>[^"]+)" does not exist$/
     *
     * @param string $dir
     */
    public function directoryDoesNotExist($dir)
    {
        $fullPath = $this->getFullPath($dir);

        $directoryInfo = new \SplFileInfo($fullPath);

        if ($directoryInfo->isDir()) {
            rmdir($directoryInfo->getRealPath());
        }
    }


    /**
     * @Then /^I should see the "(?P<dir>[^"]+)" directory exists$/
     *
     * @param string $dir
     *
     * @throws DirectoryDoesNotExistException
     */
    public function iShouldSeeTheDirectoryExists($dir)
    {
        $fullPath = $this->getFullPath($dir);

        $directoryInfo = new \SplFileInfo($fullPath);

        if (!$directoryInfo->isDir()) {
            throw DirectoryDoesNotExistException::create($dir);
        }
    }


    /**
     * @param $dir
     *
     * @return string
     */
    public function getFullPath($dir)
    {
        return $this->basePath . '/' . trim($dir, '/');
    }
}